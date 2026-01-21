<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpressionBesoin;
use App\Models\DemandeAchat;
use App\Services\NumerotationService;
use App\Enums\StatutEB;
use App\Enums\StatutDA;
use App\Enums\TypeDemande;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ExpressionBesoinController extends Controller
{
    protected NumerotationService $numerotation;

    public function __construct(NumerotationService $numerotation)
    {
        $this->numerotation = $numerotation;
    }

    /**
     * Liste des expressions de besoins
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExpressionBesoin::with(['zone', 'direction', 'service', 'demandeur', 'acheteur'])
            ->orderBy('created_at', 'desc');

        // Filtres
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('direction_id')) {
            $query->where('direction_id', $request->direction_id);
        }

        if ($request->has('acheteur_id')) {
            $query->where('acheteur_id', $request->acheteur_id);
        }

        if ($request->has('date_debut')) {
            $query->whereDate('date_expression', '>=', $request->date_debut);
        }

        if ($request->has('date_fin')) {
            $query->whereDate('date_expression', '<=', $request->date_fin);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('objet', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $data = $query->paginate($perPage);

        return response()->json($data);
    }

    /**
     * Créer une nouvelle expression de besoin
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'nullable|uuid|exists:zones,id',
            'direction_id' => 'required|uuid|exists:directions,id',
            'service_id' => 'nullable|uuid|exists:services,id',
            'objet' => 'required|string|max:500',
            'description_detaillee' => 'nullable|string',
            'quantite_souhaitee' => 'nullable|string',
            'date_besoin' => 'nullable|date',
            'estimation' => 'nullable|numeric|min:0',
            'fournisseur_suggere_id' => 'nullable|uuid|exists:fournisseurs,id',
            'commentaire' => 'nullable|string',
        ]);

        $user = auth()->user();
        $societeId = $user->service->direction->zone->societe_id;
        // Si zone non spécifiée, utiliser la zone de l'utilisateur
        $zoneId = $validated['zone_id'] ?? $user->service->direction->zone_id;

        $eb = DB::transaction(function () use ($validated, $user, $societeId, $zoneId) {
            $numero = $this->numerotation->genererNumero($societeId, 'EB');

            return ExpressionBesoin::create([
                'societe_id' => $societeId,
                'numero' => $numero,
                'date_expression' => now(),
                'zone_id' => $zoneId,
                'direction_id' => $validated['direction_id'],
                'service_id' => $validated['service_id'] ?? null,
                'demandeur_id' => $user->id,
                'objet' => $validated['objet'],
                'description_detaillee' => $validated['description_detaillee'] ?? null,
                'quantite_souhaitee' => $validated['quantite_souhaitee'] ?? null,
                'date_besoin' => $validated['date_besoin'] ?? null,
                'estimation' => $validated['estimation'] ?? null,
                'fournisseur_suggere_id' => $validated['fournisseur_suggere_id'] ?? null,
                'commentaire' => $validated['commentaire'] ?? null,
                'statut' => StatutEB::EN_SUSPENS,
                'created_by' => $user->id,
            ]);
        });

        return response()->json([
            'message' => 'Expression de besoin créée avec succès',
            'data' => $eb->load(['zone', 'direction', 'service', 'demandeur']),
        ], 201);
    }

    /**
     * Afficher une expression de besoin
     */
    public function show(string $id): JsonResponse
    {
        $eb = ExpressionBesoin::with([
            'zone', 
            'direction', 
            'service', 
            'demandeur', 
            'acheteur',
            'fournisseurSuggere',
            'demandesAchat',
            'bonsCommande'
        ])->findOrFail($id);

        return response()->json($eb);
    }

    /**
     * Mettre à jour une expression de besoin
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $eb = ExpressionBesoin::findOrFail($id);

        // Vérifier si modifiable
        if (!$eb->statut->canEdit()) {
            return response()->json([
                'message' => 'Cette expression de besoin ne peut plus être modifiée'
            ], 422);
        }

        $validated = $request->validate([
            'numero' => 'sometimes|string|max:50|unique:expressions_besoin,numero,' . $id,
            'date_expression' => 'sometimes|date',
            'zone_id' => 'sometimes|uuid|exists:zones,id',
            'direction_id' => 'sometimes|uuid|exists:directions,id',
            'service_id' => 'nullable|uuid|exists:services,id',
            'demandeur_id' => 'nullable|uuid|exists:users,id',
            'demandeur_nom' => 'nullable|string|max:255',
            'objet' => 'sometimes|string|max:500',
            'description_detaillee' => 'nullable|string',
            'quantite_souhaitee' => 'nullable|string',
            'date_besoin' => 'nullable|date',
            'estimation' => 'nullable|numeric|min:0',
            'fournisseur_suggere_id' => 'nullable|uuid|exists:fournisseurs,id',
            'commentaire' => 'nullable|string',
        ]);

        $eb->update($validated);

        return response()->json([
            'message' => 'Expression de besoin mise à jour',
            'data' => $eb->fresh(['zone', 'direction', 'service', 'demandeur']),
        ]);
    }

    /**
     * Supprimer une expression de besoin
     */
    public function destroy(string $id): JsonResponse
    {
        $eb = ExpressionBesoin::findOrFail($id);

        if (!$eb->statut->canCancel()) {
            return response()->json([
                'message' => 'Cette expression de besoin ne peut pas être supprimée'
            ], 422);
        }

        $eb->forceDelete();

        return response()->json([
            'message' => 'Expression de besoin supprimée définitivement'
        ]);
    }

    /**
     * Assigner un acheteur à l'EB
     */
    public function assigner(Request $request, string $id): JsonResponse
    {
        $eb = ExpressionBesoin::findOrFail($id);

        $validated = $request->validate([
            'acheteur_id' => 'required|uuid|exists:users,id',
        ]);

        $eb->update([
            'acheteur_id' => $validated['acheteur_id'],
            'statut' => StatutEB::EN_COURS_ACH,
        ]);

        return response()->json([
            'message' => 'Acheteur assigné avec succès',
            'data' => $eb->fresh(['acheteur']),
        ]);
    }

    /**
     * Valider l'EB (passer à l'étape suivante)
     */
    public function valider(Request $request, string $id): JsonResponse
    {
        $eb = ExpressionBesoin::findOrFail($id);
        $user = auth()->user();

        $validated = $request->validate([
            'commentaire' => 'nullable|string',
        ]);

        // Déterminer le prochain statut selon le rôle
        $nouveauStatut = $this->determinerProchainStatut($eb, $user);

        if (!$nouveauStatut) {
            return response()->json([
                'message' => 'Vous n\'êtes pas autorisé à valider cette expression de besoin'
            ], 403);
        }

        $eb->update([
            'statut' => $nouveauStatut,
            'commentaire' => $validated['commentaire'] ?? $eb->commentaire,
        ]);

        if ($nouveauStatut === StatutEB::TRAITE) {
            $eb->update(['date_validation' => now()]);
        }

        return response()->json([
            'message' => 'Expression de besoin validée',
            'data' => $eb->fresh(),
        ]);
    }

    /**
     * Rejeter l'EB
     */
    public function rejeter(Request $request, string $id): JsonResponse
    {
        $eb = ExpressionBesoin::findOrFail($id);

        $validated = $request->validate([
            'motif_rejet' => 'required|string|max:1000',
        ]);

        $eb->update([
            'statut' => StatutEB::ANNULE,
            'motif_rejet' => $validated['motif_rejet'],
        ]);

        return response()->json([
            'message' => 'Expression de besoin rejetée',
            'data' => $eb->fresh(),
        ]);
    }

    /**
     * Transformer l'EB en Demande d'Achat
     */
    public function transformer(Request $request, string $id): JsonResponse
    {
        $eb = ExpressionBesoin::findOrFail($id);

        if ($eb->statut !== StatutEB::EN_COURS_ACH) {
            return response()->json([
                'message' => 'Cette expression de besoin ne peut pas être transformée'
            ], 422);
        }

        $validated = $request->validate([
            'type_demande' => 'required|in:DA,DAC',
            'montant' => 'required|numeric|min:0',
        ]);

        $user = auth()->user();
        $typeDemande = TypeDemande::from($validated['type_demande']);
        $typeDoc = $typeDemande === TypeDemande::DAC ? 'DAC' : 'DA';

        $da = DB::transaction(function () use ($eb, $validated, $user, $typeDemande, $typeDoc) {
            $numero = $this->numerotation->genererNumero($eb->societe_id, $typeDoc);

            $da = DemandeAchat::create([
                'societe_id' => $eb->societe_id,
                'numero' => $numero,
                'type_demande' => $typeDemande,
                'date_demande' => now(),
                'expression_besoin_id' => $eb->id,
                'zone_id' => $eb->zone_id,
                'direction_id' => $eb->direction_id,
                'service_id' => $eb->service_id,
                'demandeur_id' => $eb->demandeur_id,
                'objet' => $eb->objet,
                'description' => $eb->description_detaillee,
                'acheteur_id' => $eb->acheteur_id,
                'montant' => $validated['montant'],
                'statut' => StatutDA::EN_COURS_ACH,
                'created_by' => $user->id,
            ]);

            // Mettre à jour l'EB
            $eb->update([
                'statut' => StatutEB::TRAITE,
                'date_validation' => now(),
            ]);

            return $da;
        });

        return response()->json([
            'message' => 'Expression de besoin transformée en demande d\'achat',
            'data' => $da->load(['zone', 'direction', 'service', 'demandeur', 'acheteur']),
        ], 201);
    }

    /**
     * Liste des statuts pour dropdown
     */
    public function listeStatuts(): JsonResponse
    {
        $statuts = collect(StatutEB::cases())->map(fn($s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ]);

        return response()->json($statuts);
    }

    /**
     * Déterminer le prochain statut selon le rôle utilisateur
     */
    protected function determinerProchainStatut(ExpressionBesoin $eb, $user): ?StatutEB
    {
        $montant = $eb->estimation ?? 0;
        $seuils = config('ct_achats.seuils');

        // Logique de validation selon les rôles et seuils
        if ($user->hasRole('ACHETEUR') && $eb->statut === StatutEB::EN_SUSPENS) {
            return StatutEB::EN_COURS_ACH;
        }

        if ($user->hasRole('VALIDEUR_CDG') && $eb->statut === StatutEB::EN_COURS_ACH) {
            if ($montant >= $seuils['dfc']) {
                return StatutEB::EN_COURS_DFC;
            }
            return StatutEB::TRAITE;
        }

        if ($user->hasRole('VALIDEUR_DFC') && $eb->statut === StatutEB::EN_COURS_CDG) {
            if ($montant >= $seuils['dg']) {
                return StatutEB::EN_COURS_DG;
            }
            return StatutEB::TRAITE;
        }

        if ($user->hasRole('VALIDEUR_DG') && $eb->statut === StatutEB::EN_COURS_DFC) {
            return StatutEB::TRAITE;
        }

        return null;
    }
}
