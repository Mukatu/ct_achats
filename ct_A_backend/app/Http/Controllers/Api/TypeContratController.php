<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TypeContrat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TypeContratController extends Controller
{
    /**
     * Liste des types de contrat
     */
    public function index(Request $request): JsonResponse
    {
        $query = TypeContrat::query();

        if ($request->boolean('actif_only', true)) {
            $query->actif();
        }

        $types = $query->orderBy('libelle')->get();

        return response()->json($types);
    }

    /**
     * Creer un nouveau type de contrat
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:types_contrat,code',
            'libelle' => 'required|string|max:100',
            'description' => 'nullable|string',
            'actif' => 'boolean',
        ]);

        $type = TypeContrat::create($validated);

        return response()->json([
            'message' => 'Type de contrat cree avec succes',
            'data' => $type,
        ], 201);
    }

    /**
     * Afficher un type de contrat
     */
    public function show(string $id): JsonResponse
    {
        $type = TypeContrat::withCount('contrats')->findOrFail($id);

        return response()->json($type);
    }

    /**
     * Mettre a jour un type de contrat
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $type = TypeContrat::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|string|max:20|unique:types_contrat,code,' . $id,
            'libelle' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'actif' => 'boolean',
        ]);

        $type->update($validated);

        return response()->json([
            'message' => 'Type de contrat mis a jour',
            'data' => $type,
        ]);
    }

    /**
     * Supprimer un type de contrat
     */
    public function destroy(string $id): JsonResponse
    {
        $type = TypeContrat::withCount('contrats')->findOrFail($id);

        if ($type->contrats_count > 0) {
            return response()->json([
                'message' => 'Ce type est utilise par des contrats et ne peut pas etre supprime'
            ], 422);
        }

        $type->delete();

        return response()->json([
            'message' => 'Type de contrat supprime'
        ]);
    }
}
