<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UniteMesure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UniteMesureController extends Controller
{
    public function index(): JsonResponse
    {
        $unites = UniteMesure::orderBy('libelle')->get();
        return response()->json($unites);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:unites_mesure',
            'libelle' => 'required|string|max:100',
            'symbole' => 'required|string|max:10',
            'actif' => 'boolean',
        ]);

        $validated['actif'] = $validated['actif'] ?? true;
        $unite = UniteMesure::create($validated);

        return response()->json($unite, 201);
    }

    public function show(UniteMesure $uniteMesure): JsonResponse
    {
        return response()->json($uniteMesure);
    }

    public function update(Request $request, UniteMesure $uniteMesure): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:10|unique:unites_mesure,code,' . $uniteMesure->id,
            'libelle' => 'sometimes|string|max:100',
            'symbole' => 'sometimes|string|max:10',
            'actif' => 'sometimes|boolean',
        ]);

        $uniteMesure->update($validated);
        return response()->json($uniteMesure);
    }

    public function destroy(UniteMesure $uniteMesure): JsonResponse
    {
        $uniteMesure->delete();
        return response()->json(['message' => 'Unité supprimée']);
    }
}
