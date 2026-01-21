<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemandeAchat;
use App\Models\BonCommande;
use App\Services\NumerotationService;
use App\Enums\StatutDA;
use App\Enums\StatutBC;
use App\Enums\TypeBC;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DemandeAchatController extends Controller
{
    protected NumerotationService $numerotation;

    public function __construct(NumerotationService $numerotation)
    {
        $this->numerotation = $numerotation;
    }

    public function index(Request $request): JsonResponse
    {
        $query = DemandeAchat::with(['zone', 'direction', 'service', 'demandeur', 'acheteur'])
            ->orderBy('created_at', 'desc');

        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('type_demande')) {
            $query->where('type_demande', $request->type_demande);
        }

        if ($request->has('direction_id')) {
            $query->where('direction_id', $request->direction_id);
        }

        if ($request->has('acheteur_id')) {
            $query->where('acheteur_id', $request->acheteur_id);
        }

        if ($request->has('date_debut')) {
            $query->whereDate('date_demande', '>=', $request->date_debut);
        }

        if ($request->has('date_fin')) {
            $query->whereDate('date_demande', '<=', $request->date_fin);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('objet', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'nullable|uuid|exists:zones,id',
            'direction_id' => 'required|uuid|exists:directions,id',
            'service_id' => 'nullable|uuid|exists:services,id',
            'type_demande' => 'required|in:DA,DAC',
            'objet' => 'required|string|max:500',
            'description' => 'nullable|string',
            'montant' => 'required|numeric|min:0',
        ]);

        $user = auth()->user();
        $societeId = $user->service->direction->zone->societe_id;
        // Si zone non spécifiée, utiliser la zone de l'utilisateur
        $zoneId = $validated['zone_id'] ?? $user->service->direction->zone_id;
        $typeDoc = $validated['type_demande'];

        $da = DB::transaction(function () use ($validated, $user, $societeId, $zoneId, $typeDoc) {
            $numero = $this->numerotation->genererNumero($societeId, $typeDoc);

            return DemandeAchat::create([
                'societe_id' => $societeId,
                'numero' => $numero,
                'type_demande' => $validated['type_demande'],
                'date_demande' => now(),
                'zone_id' => $zoneId,
                'direction_id' => $validated['direction_id'],
                'service_id' => $validated['service_id'] ?? null,
                'demandeur_id' => $user->id,
                'objet' => $validated['objet'],
                'description' => $validated['description'] ?? null,
                'montant' => $validated['montant'],
                'statut' => StatutDA::EN_COURS_ACH,
                'created_by' => $user->id,
            ]);
        });

        return response()->json([
            'message' => 'Demande d\'achat créée',
            'data' => $da->load(['zone', 'direction', 'service', 'demandeur']),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $da = DemandeAchat::with([
            'zone', 'direction', 'service', 'demandeur', 'acheteur',
            'expressionBesoin', 'bonsCommande',
            'lignes.uniteMesure', 'lignes.offres.fournisseur', 'lignes.offreSelectionnee.fournisseur'
        ])->findOrFail($id);

        return response()->json($da);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $da = DemandeAchat::findOrFail($id);

        $validated = $request->validate([
            'objet' => 'sometimes|string|max:500',
            'description' => 'nullable|string',
            'montant' => 'sometimes|numeric|min:0',
        ]);

        $da->update($validated);
        return response()->json(['message' => 'Demande mise à jour', 'data' => $da->fresh()]);
    }

    public function destroy(string $id): JsonResponse
    {
        $da = DemandeAchat::findOrFail($id);

        // Supprimer d'abord les lignes et leurs offres
        foreach ($da->lignes as $ligne) {
            $ligne->offres()->forceDelete();
            $ligne->forceDelete();
        }

        $da->forceDelete();
        return response()->json(['message' => 'Demande supprimée définitivement']);
    }

    public function valider(Request $request, string $id): JsonResponse
    {
        $da = DemandeAchat::findOrFail($id);
        $user = auth()->user();

        $validated = $request->validate([
            'commentaire' => 'nullable|string',
        ]);

        // Déterminer le prochain statut selon le rôle
        $nouveauStatut = $this->determinerProchainStatut($da, $user);

        if (!$nouveauStatut) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à valider cette demande d\'achat'
            ], 403);
        }

        $da->update([
            'statut' => $nouveauStatut,
        ]);

        if ($nouveauStatut === StatutDA::TRAITE) {
            $da->update(['date_validation' => now()]);
        }

        return response()->json([
            'message' => 'Demande validée',
            'data' => $da->fresh(['zone', 'direction', 'service', 'demandeur', 'acheteur']),
        ]);
    }

    /**
     * Déterminer le prochain statut selon le rôle utilisateur
     */
    protected function determinerProchainStatut(DemandeAchat $da, $user): ?StatutDA
    {
        $montant = $da->montant ?? 0;
        $seuils = config('ct_achats.seuils', [
            'cdg' => 500000,
            'dfc' => 5000000,
            'dg' => 50000000
        ]);

        // Workflow simplifié : EN_COURS_ACH -> EN_COURS_CDG -> EN_COURS_DFC -> EN_COURS_DG -> TRAITE
        // Selon les montants et rôles

        if ($da->statut === StatutDA::EN_COURS_ACH) {
            if ($montant < $seuils['cdg']) {
                return StatutDA::TRAITE;
            }
            return StatutDA::EN_COURS_CDG;
        }

        if ($da->statut === StatutDA::EN_COURS_CDG) {
            if ($montant < $seuils['dfc']) {
                return StatutDA::TRAITE;
            }
            return StatutDA::EN_COURS_DFC;
        }

        if ($da->statut === StatutDA::EN_COURS_DFC) {
            if ($montant < $seuils['dg']) {
                return StatutDA::TRAITE;
            }
            return StatutDA::EN_COURS_DG;
        }

        if ($da->statut === StatutDA::EN_COURS_DG) {
            return StatutDA::TRAITE;
        }

        return null;
    }

    public function rejeter(Request $request, string $id): JsonResponse
    {
        $da = DemandeAchat::findOrFail($id);
        $validated = $request->validate(['motif_rejet' => 'required|string']);

        $da->update([
            'statut' => StatutDA::ANNULE,
            'motif_rejet' => $validated['motif_rejet'],
        ]);

        return response()->json(['message' => 'Demande rejetée', 'data' => $da]);
    }

    public function transformer(Request $request, string $id): JsonResponse
    {
        $da = DemandeAchat::findOrFail($id);

        // Bloquer la transformation des DAC en BC
        // Les DAC sont des dépenses caisse directes, pas des bons de commande
        if ($da->isDac()) {
            return response()->json([
                'message' => 'Les Demandes d\'Achat Caisse (DAC) ne peuvent pas être transformées en Bon de Commande. Utilisez la clôture avec pièce justificative.'
            ], 422);
        }

        $validated = $request->validate([
            'fournisseur_id' => 'required|uuid|exists:fournisseurs,id',
            'type_bc' => 'required|in:BCAL,BCL,BCAI,BCI,IPO',
        ]);

        $user = auth()->user();

        $bc = DB::transaction(function () use ($da, $validated, $user) {
            $numero = $this->numerotation->genererNumero($da->societe_id, 'BC');

            $bc = BonCommande::create([
                'societe_id' => $da->societe_id,
                'numero' => $numero,
                'type_bc' => $validated['type_bc'],
                'date_bc' => now(),
                'demande_achat_id' => $da->id,
                'expression_besoin_id' => $da->expression_besoin_id,
                'fournisseur_id' => $validated['fournisseur_id'],
                'zone_id' => $da->zone_id,
                'direction_id' => $da->direction_id,
                'demandeur_id' => $da->demandeur_id,
                'acheteur_id' => $da->acheteur_id ?? $user->id,
                'objet' => $da->objet,
                'montant_ht_xaf' => $da->montant,
                'taux_tva' => 18.00,
                'statut' => StatutBC::NC,
                'created_by' => $user->id,
            ]);

            $da->update(['statut' => StatutDA::TRAITE]);

            return $bc;
        });

        return response()->json([
            'message' => 'Bon de commande créé',
            'data' => $bc->load(['fournisseur', 'demandeAchat']),
        ], 201);
    }

    public function listeStatuts(): JsonResponse
    {
        return response()->json(
            collect(StatutDA::cases())->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])
        );
    }

    public function listeUnites(): JsonResponse
    {
        return response()->json(
            DB::table('unites_mesure')
                ->where('actif', true)
                ->select('id as value', 'libelle as label', 'symbole')
                ->get()
        );
    }

    public function listeNatures(): JsonResponse
    {
        return response()->json(
            DB::table('natures_depense')
                ->where('actif', true)
                ->select('id as value', 'libelle as label', 'code')
                ->get()
        );
    }

    /**
     * Clôturer une DAC avec pièce justificative
     * Les DAC ne passent pas par les BC mais sont des engagements directs
     */
    public function cloturerDac(Request $request, string $id): JsonResponse
    {
        $da = DemandeAchat::findOrFail($id);

        // Vérifier que c'est bien une DAC
        if (!$da->isDac()) {
            return response()->json([
                'message' => 'Cette opération est réservée aux Demandes d\'Achat Caisse (DAC)'
            ], 422);
        }

        // Vérifier que la DAC est au statut TRAITE
        if ($da->statut !== StatutDA::TRAITE) {
            return response()->json([
                'message' => 'La DAC doit être au statut "Traitée" pour être clôturée'
            ], 422);
        }

        // Vérifier que la DAC n'est pas déjà clôturée
        if ($da->isCloturee()) {
            return response()->json([
                'message' => 'Cette DAC est déjà clôturée'
            ], 422);
        }

        $validated = $request->validate([
            'date_paiement_caisse' => 'required|date',
            'reference_paiement' => 'required|string|max:100',
            'montant_paye' => 'required|numeric|min:0',
            'piece_justificative' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'observations_cloture' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();

        // Gestion du fichier justificatif
        $pieceJustificativePath = null;
        if ($request->hasFile('piece_justificative')) {
            $pieceJustificativePath = $request->file('piece_justificative')
                ->store('dac/justificatifs/' . date('Y/m'), 'public');
        }

        $da->update([
            'date_paiement_caisse' => $validated['date_paiement_caisse'],
            'reference_paiement' => $validated['reference_paiement'],
            'montant_paye' => $validated['montant_paye'],
            'piece_justificative' => $pieceJustificativePath,
            'observations_cloture' => $validated['observations_cloture'] ?? null,
            'cloture_par' => $user->id,
            'date_cloture' => now(),
        ]);

        return response()->json([
            'message' => 'DAC clôturée avec succès',
            'data' => $da->fresh(['zone', 'direction', 'service', 'demandeur', 'cloturePar']),
        ]);
    }
}
