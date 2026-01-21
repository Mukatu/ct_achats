<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemandeAchat;
use App\Models\LigneDemandeAchat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LigneDemandeAchatController extends Controller
{
    /**
     * Liste des lignes d'une DA
     */
    public function index(string $daId): JsonResponse
    {
        $lignes = LigneDemandeAchat::with(['uniteMesure', 'offres.fournisseur', 'offreSelectionnee.fournisseur'])
            ->where('demande_achat_id', $daId)
            ->orderBy('numero_ligne')
            ->get();

        return response()->json($lignes);
    }

    /**
     * Créer une nouvelle ligne
     */
    public function store(Request $request, string $daId): JsonResponse
    {
        $da = DemandeAchat::findOrFail($daId);

        $validated = $request->validate([
            'designation' => 'required|string|max:255',
            'description' => 'nullable|string',
            'quantite' => 'required|numeric|min:0.01',
            'unite_mesure_id' => 'nullable|uuid|exists:unites_mesure,id',
            'prix_unitaire_estime' => 'nullable|numeric|min:0',
        ]);

        // Déterminer le prochain numéro de ligne
        $maxLigne = LigneDemandeAchat::where('demande_achat_id', $daId)->max('numero_ligne') ?? 0;

        $ligne = LigneDemandeAchat::create([
            'demande_achat_id' => $daId,
            'numero_ligne' => $maxLigne + 1,
            'designation' => $validated['designation'],
            'description' => $validated['description'] ?? null,
            'quantite' => $validated['quantite'],
            'unite_mesure_id' => $validated['unite_mesure_id'] ?? null,
            'prix_unitaire_estime' => $validated['prix_unitaire_estime'] ?? null,
            'montant_estime' => isset($validated['prix_unitaire_estime'])
                ? $validated['prix_unitaire_estime'] * $validated['quantite']
                : null,
        ]);

        return response()->json([
            'message' => 'Ligne créée avec succès',
            'data' => $ligne->load(['uniteMesure', 'offres.fournisseur']),
        ], 201);
    }

    /**
     * Afficher une ligne
     */
    public function show(string $daId, string $id): JsonResponse
    {
        $ligne = LigneDemandeAchat::with(['uniteMesure', 'offres.fournisseur', 'offreSelectionnee.fournisseur'])
            ->where('demande_achat_id', $daId)
            ->findOrFail($id);

        return response()->json($ligne);
    }

    /**
     * Mettre à jour une ligne
     */
    public function update(Request $request, string $daId, string $id): JsonResponse
    {
        $ligne = LigneDemandeAchat::where('demande_achat_id', $daId)->findOrFail($id);

        $validated = $request->validate([
            'designation' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'quantite' => 'sometimes|numeric|min:0.01',
            'unite_mesure_id' => 'nullable|uuid|exists:unites_mesure,id',
            'prix_unitaire_estime' => 'nullable|numeric|min:0',
        ]);

        $ligne->update($validated);

        // Recalculer le montant estimé si nécessaire
        if (isset($validated['quantite']) || isset($validated['prix_unitaire_estime'])) {
            $ligne->montant_estime = $ligne->prix_unitaire_estime
                ? $ligne->prix_unitaire_estime * $ligne->quantite
                : null;
            $ligne->save();
        }

        return response()->json([
            'message' => 'Ligne mise à jour',
            'data' => $ligne->fresh(['uniteMesure', 'offres.fournisseur']),
        ]);
    }

    /**
     * Supprimer une ligne
     */
    public function destroy(string $daId, string $id): JsonResponse
    {
        $ligne = LigneDemandeAchat::where('demande_achat_id', $daId)->findOrFail($id);

        // Supprimer les offres associées
        $ligne->offres()->forceDelete();
        $ligne->forceDelete();

        // Renuméroter les lignes restantes
        $this->renumeroterLignes($daId);

        return response()->json(['message' => 'Ligne supprimée définitivement']);
    }

    /**
     * Sélectionner une offre pour une ligne
     */
    public function selectOffer(Request $request, string $daId, string $id): JsonResponse
    {
        $ligne = LigneDemandeAchat::where('demande_achat_id', $daId)->findOrFail($id);

        $validated = $request->validate([
            'offre_id' => 'required|uuid|exists:offres_fournisseur,id',
        ]);

        // Vérifier que l'offre appartient à cette ligne
        $offreExists = $ligne->offres()->where('id', $validated['offre_id'])->exists();
        if (!$offreExists) {
            return response()->json(['message' => 'Cette offre n\'appartient pas à cette ligne'], 422);
        }

        // Désélectionner les autres offres
        $ligne->offres()->update(['est_selectionnee' => false]);

        // Sélectionner la nouvelle offre
        DB::table('offres_fournisseur')
            ->where('id', $validated['offre_id'])
            ->update(['est_selectionnee' => true]);

        $ligne->update(['offre_selectionnee_id' => $validated['offre_id']]);

        return response()->json([
            'message' => 'Offre sélectionnée',
            'data' => $ligne->fresh(['uniteMesure', 'offres.fournisseur', 'offreSelectionnee.fournisseur']),
        ]);
    }

    /**
     * Renuméroter les lignes après suppression
     */
    private function renumeroterLignes(string $daId): void
    {
        $lignes = LigneDemandeAchat::where('demande_achat_id', $daId)
            ->orderBy('numero_ligne')
            ->get();

        $numero = 1;
        foreach ($lignes as $ligne) {
            if ($ligne->numero_ligne !== $numero) {
                $ligne->update(['numero_ligne' => $numero]);
            }
            $numero++;
        }
    }
}
