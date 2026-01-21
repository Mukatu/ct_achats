<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NatureDepense;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NatureDepenseController extends Controller
{
    public function index(): JsonResponse
    {
        $natures = NatureDepense::with('parent')
            ->orderBy('code')
            ->get();
        return response()->json($natures);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:natures_depense',
            'libelle' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|max:50',
            'compte_ohada' => 'nullable|string|max:20',
            'parent_id' => 'nullable|uuid|exists:natures_depense,id',
            'niveau' => 'nullable|integer|min:1',
            'imputable' => 'boolean',
            'actif' => 'boolean',
        ]);

        $validated['actif'] = $validated['actif'] ?? true;
        $validated['imputable'] = $validated['imputable'] ?? true;
        $nature = NatureDepense::create($validated);

        return response()->json($nature, 201);
    }

    public function show(NatureDepense $natureDepense): JsonResponse
    {
        return response()->json($natureDepense->load('parent'));
    }

    public function update(Request $request, NatureDepense $natureDepense): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:10|unique:natures_depense,code,' . $natureDepense->id,
            'libelle' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string|max:50',
            'compte_ohada' => 'nullable|string|max:20',
            'parent_id' => 'nullable|uuid|exists:natures_depense,id',
            'niveau' => 'nullable|integer|min:1',
            'imputable' => 'sometimes|boolean',
            'actif' => 'sometimes|boolean',
        ]);

        $natureDepense->update($validated);
        return response()->json($natureDepense);
    }

    public function destroy(NatureDepense $natureDepense): JsonResponse
    {
        // Vérifier s'il y a des enfants
        if ($natureDepense->children()->count() > 0) {
            return response()->json([
                'message' => 'Cette nature a des sous-natures et ne peut pas être supprimée'
            ], 422);
        }

        $natureDepense->delete();
        return response()->json(['message' => 'Nature de dépense supprimée']);
    }
}
