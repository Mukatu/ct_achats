<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CriterePonderation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CriterePonderationController extends Controller
{
    /**
     * Liste des critères de pondération
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $societeId = $user->service?->direction?->zone?->societe_id;

        $criteres = CriterePonderation::where('societe_id', $societeId)
            ->orderBy('ordre')
            ->get();

        return response()->json($criteres);
    }

    /**
     * Mettre à jour les pondérations
     */
    public function updatePoids(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ponderations' => 'required|array',
            'ponderations.*.id' => 'required|uuid|exists:criteres_ponderation,id',
            'ponderations.*.poids' => 'required|integer|min:0|max:100',
        ]);

        // Vérifier que le total fait 100
        $total = collect($validated['ponderations'])->sum('poids');
        if ($total !== 100) {
            return response()->json([
                'message' => 'Le total des pondérations doit être égal à 100%',
                'total_actuel' => $total,
            ], 422);
        }

        foreach ($validated['ponderations'] as $pond) {
            CriterePonderation::where('id', $pond['id'])->update(['poids' => $pond['poids']]);
        }

        return response()->json([
            'message' => 'Pondérations mises à jour avec succès',
        ]);
    }

    /**
     * Activer/Désactiver un critère
     */
    public function toggle(string $id): JsonResponse
    {
        $critere = CriterePonderation::findOrFail($id);
        $critere->update(['actif' => !$critere->actif]);

        return response()->json([
            'message' => $critere->actif ? 'Critère activé' : 'Critère désactivé',
            'data' => $critere,
        ]);
    }
}
