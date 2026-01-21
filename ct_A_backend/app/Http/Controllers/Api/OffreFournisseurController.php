<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LigneDemandeAchat;
use App\Models\OffreFournisseur;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OffreFournisseurController extends Controller
{
    /**
     * Liste des offres d'une ligne
     */
    public function index(string $ligneId): JsonResponse
    {
        $offres = OffreFournisseur::with(['fournisseur'])
            ->where('ligne_demande_achat_id', $ligneId)
            ->orderByDesc('score')
            ->get();

        return response()->json($offres);
    }

    /**
     * Créer une nouvelle offre
     */
    public function store(Request $request, string $ligneId): JsonResponse
    {
        $ligne = LigneDemandeAchat::findOrFail($ligneId);

        $validated = $request->validate([
            'fournisseur_id' => 'required|uuid|exists:fournisseurs,id',
            'prix_unitaire' => 'required|numeric|min:0',
            'delai_livraison_jours' => 'required|integer|min:1',
            'conditions_paiement_jours' => 'required|integer|min:0',
            'garantie' => 'nullable|string|max:100',
            'garantie_mois' => 'nullable|integer|min:0',
            'offre_technique' => 'required|in:CONFORME,NON_CONFORME',
            'commentaire' => 'nullable|string',
            'reference_offre' => 'nullable|string|max:100',
            'date_offre' => 'nullable|date',
            'date_validite' => 'nullable|date|after_or_equal:date_offre',
        ]);

        // Vérifier qu'il n'y a pas déjà une offre de ce fournisseur pour cette ligne
        $existingOffer = OffreFournisseur::where('ligne_demande_achat_id', $ligneId)
            ->where('fournisseur_id', $validated['fournisseur_id'])
            ->first();

        if ($existingOffer) {
            return response()->json([
                'message' => 'Ce fournisseur a déjà une offre pour cette ligne'
            ], 422);
        }

        $offre = OffreFournisseur::create([
            'ligne_demande_achat_id' => $ligneId,
            ...$validated,
        ]);

        // Recalculer les scores de toutes les offres de la ligne
        OffreFournisseur::recalculerScoresLigne($ligneId);

        return response()->json([
            'message' => 'Offre créée avec succès',
            'data' => $offre->fresh(['fournisseur']),
        ], 201);
    }

    /**
     * Afficher une offre
     */
    public function show(string $ligneId, string $id): JsonResponse
    {
        $offre = OffreFournisseur::with(['fournisseur', 'ligneDemandeAchat'])
            ->where('ligne_demande_achat_id', $ligneId)
            ->findOrFail($id);

        return response()->json($offre);
    }

    /**
     * Mettre à jour une offre
     */
    public function update(Request $request, string $ligneId, string $id): JsonResponse
    {
        $offre = OffreFournisseur::where('ligne_demande_achat_id', $ligneId)->findOrFail($id);

        $validated = $request->validate([
            'prix_unitaire' => 'sometimes|numeric|min:0',
            'delai_livraison_jours' => 'sometimes|integer|min:1',
            'conditions_paiement_jours' => 'sometimes|integer|min:0',
            'garantie' => 'nullable|string|max:100',
            'garantie_mois' => 'nullable|integer|min:0',
            'offre_technique' => 'sometimes|in:CONFORME,NON_CONFORME',
            'commentaire' => 'nullable|string',
            'reference_offre' => 'nullable|string|max:100',
            'date_offre' => 'nullable|date',
            'date_validite' => 'nullable|date',
        ]);

        $offre->update($validated);

        // Recalculer les scores de toutes les offres de la ligne
        OffreFournisseur::recalculerScoresLigne($ligneId);

        return response()->json([
            'message' => 'Offre mise à jour',
            'data' => $offre->fresh(['fournisseur']),
        ]);
    }

    /**
     * Supprimer une offre
     */
    public function destroy(string $ligneId, string $id): JsonResponse
    {
        $offre = OffreFournisseur::where('ligne_demande_achat_id', $ligneId)->findOrFail($id);

        // Si c'était l'offre sélectionnée, la désélectionner
        if ($offre->est_selectionnee) {
            LigneDemandeAchat::where('id', $ligneId)->update(['offre_selectionnee_id' => null]);
        }

        $offre->forceDelete();

        // Recalculer les scores des offres restantes
        OffreFournisseur::recalculerScoresLigne($ligneId);

        return response()->json(['message' => 'Offre supprimée définitivement']);
    }

    /**
     * Comparaison des offres d'une ligne avec détails
     */
    public function compare(string $ligneId): JsonResponse
    {
        $ligne = LigneDemandeAchat::with(['uniteMesure', 'demandeAchat.societe'])
            ->findOrFail($ligneId);

        $offres = OffreFournisseur::with(['fournisseur'])
            ->where('ligne_demande_achat_id', $ligneId)
            ->orderByDesc('score')
            ->get();

        // Calculer les stats pour la comparaison
        $conformes = $offres->where('offre_technique', 'CONFORME');

        $stats = [
            'nombre_offres' => $offres->count(),
            'nombre_conformes' => $conformes->count(),
            'meilleur_prix' => $conformes->min('prix_unitaire'),
            'pire_prix' => $conformes->max('prix_unitaire'),
            'meilleur_delai' => $conformes->min('delai_livraison_jours'),
            'meilleure_garantie' => $conformes->max('garantie_mois'),
            'meilleur_paiement' => $conformes->max('conditions_paiement_jours'),
        ];

        // Identifier la meilleure offre pour chaque critère
        $meilleures = [];
        if ($conformes->isNotEmpty()) {
            $meilleures = [
                'prix' => $conformes->sortBy('prix_unitaire')->first()?->id,
                'delai' => $conformes->sortBy('delai_livraison_jours')->first()?->id,
                'garantie' => $conformes->sortByDesc('garantie_mois')->first()?->id,
                'paiement' => $conformes->sortByDesc('conditions_paiement_jours')->first()?->id,
            ];
        }

        return response()->json([
            'ligne' => $ligne,
            'offres' => $offres,
            'stats' => $stats,
            'meilleures' => $meilleures,
        ]);
    }
}
