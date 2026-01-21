<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BonCommande;
use App\Enums\StatutBC;
use App\Enums\TypeBC;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BonCommandeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BonCommande::with(['zone', 'direction', 'demandeur', 'acheteur', 'fournisseur'])
            ->orderBy('created_at', 'desc');

        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('type_bc')) {
            $query->where('type_bc', $request->type_bc);
        }

        if ($request->has('fournisseur_id')) {
            $query->where('fournisseur_id', $request->fournisseur_id);
        }

        if ($request->has('direction_id')) {
            $query->where('direction_id', $request->direction_id);
        }

        if ($request->has('acheteur_id')) {
            $query->where('acheteur_id', $request->acheteur_id);
        }

        if ($request->has('date_debut')) {
            $query->whereDate('date_bc', '>=', $request->date_debut);
        }

        if ($request->has('date_fin')) {
            $query->whereDate('date_bc', '<=', $request->date_fin);
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
            'fournisseur_id' => 'required|uuid|exists:fournisseurs,id',
            'type_bc' => 'required|in:BCAL,BCL,BCAI,BCI,IPO',
            'zone_id' => 'nullable|uuid|exists:zones,id',
            'direction_id' => 'required|uuid|exists:directions,id',
            'objet' => 'required|string|max:500',
            'montant_ht_xaf' => 'required|numeric|min:0',
            'taux_tva' => 'numeric|min:0|max:100',
            'conditions_paiement' => 'nullable|string|max:50',
            'date_livraison_prevue' => 'nullable|date',
        ]);

        $user = auth()->user();
        $societeId = $user->service->direction->zone->societe_id;
        // Si zone non spécifiée, utiliser la zone de l'utilisateur
        $zoneId = $validated['zone_id'] ?? $user->service->direction->zone_id;

        $bc = BonCommande::create([
            'societe_id' => $societeId,
            'numero' => app(\App\Services\NumerotationService::class)->genererNumero($societeId, 'BC'),
            'type_bc' => $validated['type_bc'],
            'date_bc' => now(),
            'fournisseur_id' => $validated['fournisseur_id'],
            'zone_id' => $zoneId,
            'direction_id' => $validated['direction_id'],
            'demandeur_id' => $user->id,
            'acheteur_id' => $user->id,
            'objet' => $validated['objet'],
            'montant_ht_xaf' => $validated['montant_ht_xaf'],
            'taux_tva' => $validated['taux_tva'] ?? 18.00,
            'conditions_paiement' => $validated['conditions_paiement'] ?? null,
            'date_livraison_prevue' => $validated['date_livraison_prevue'] ?? null,
            'statut' => StatutBC::NC,
            'created_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Bon de commande créé',
            'data' => $bc->load(['fournisseur', 'zone', 'direction']),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $bc = BonCommande::with([
            'zone', 'direction', 'demandeur', 'acheteur',
            'fournisseur', 'demandeAchat', 'expressionBesoin',
            'lignes', 'receptions', 'factures'
        ])->findOrFail($id);

        return response()->json($bc);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $bc = BonCommande::findOrFail($id);

        $validated = $request->validate([
            'objet' => 'sometimes|string|max:500',
            'montant_ht_xaf' => 'sometimes|numeric|min:0',
            'conditions_paiement' => 'nullable|string|max:50',
            'date_livraison_prevue' => 'nullable|date',
            'commentaire' => 'nullable|string',
        ]);

        $bc->update($validated);
        return response()->json(['message' => 'Bon de commande mis à jour', 'data' => $bc->fresh()]);
    }

    public function destroy(string $id): JsonResponse
    {
        $bc = BonCommande::findOrFail($id);

        // Supprimer les lignes du BC
        $bc->lignes()->forceDelete();

        $bc->forceDelete();
        return response()->json(['message' => 'Bon de commande supprimé définitivement']);
    }

    public function valider(Request $request, string $id): JsonResponse
    {
        $bc = BonCommande::findOrFail($id);
        $user = auth()->user();

        // Déterminer le prochain statut selon le statut actuel
        $nouveauStatut = $this->determinerProchainStatutBC($bc);

        if (!$nouveauStatut) {
            return response()->json([
                'message' => 'Ce bon de commande ne peut pas être validé dans son état actuel'
            ], 422);
        }

        $bc->update([
            'statut' => $nouveauStatut,
        ]);

        return response()->json([
            'message' => 'Bon de commande validé',
            'data' => $bc->fresh(['zone', 'direction', 'fournisseur', 'demandeur', 'acheteur']),
        ]);
    }

    /**
     * Déterminer le prochain statut pour un BC
     */
    protected function determinerProchainStatutBC(BonCommande $bc): ?StatutBC
    {
        $montant = $bc->montant_ttc_xaf ?? $bc->montant_ht_xaf ?? 0;
        $seuils = config('ct_achats.seuils', [
            'cdg' => 500000,
            'dfc' => 5000000,
        ]);

        // Workflow BC : NC -> EN_COURS_A -> EN_COURS_CDG -> EN_COURS_DFC
        if ($bc->statut === StatutBC::NC) {
            return StatutBC::EN_COURS_A;
        }

        if ($bc->statut === StatutBC::EN_COURS_A) {
            if ($montant < $seuils['cdg']) {
                return StatutBC::EN_COURS_DFC;
            }
            return StatutBC::EN_COURS_CDG;
        }

        if ($bc->statut === StatutBC::EN_COURS_CDG) {
            return StatutBC::EN_COURS_DFC;
        }

        if ($bc->statut === StatutBC::EN_COURS_DFC) {
            // Après DFC, on peut envoyer au fournisseur (via la méthode envoyer)
            return null;
        }

        return null;
    }

    public function rejeter(Request $request, string $id): JsonResponse
    {
        $bc = BonCommande::findOrFail($id);
        $bc->update(['statut' => StatutBC::ANNULE]);
        return response()->json(['message' => 'Bon de commande rejeté', 'data' => $bc]);
    }

    public function envoyer(Request $request, string $id): JsonResponse
    {
        $bc = BonCommande::findOrFail($id);
        $bc->update([
            'statut' => StatutBC::EN_COURS_FSSEUR,
            'date_envoi_fournisseur' => now(),
        ]);
        return response()->json(['message' => 'Bon de commande envoyé au fournisseur', 'data' => $bc]);
    }

    public function genererPdf(string $id): JsonResponse
    {
        $bc = BonCommande::with(['fournisseur', 'lignes'])->findOrFail($id);
        // Génération PDF - à implémenter
        return response()->json(['message' => 'PDF généré', 'url' => null]);
    }

    public function listeStatuts(): JsonResponse
    {
        return response()->json(
            collect(StatutBC::cases())->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ])
        );
    }

    public function listeTypes(): JsonResponse
    {
        return response()->json(
            collect(TypeBC::cases())->map(fn($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ])
        );
    }

    public function conditionsPaiement(): JsonResponse
    {
        return response()->json(
            collect(config('ct_achats.conditions_paiement'))->map(fn($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values()
        );
    }
}
