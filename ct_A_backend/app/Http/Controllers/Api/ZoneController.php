<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ZoneController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Zone::withCount('directions')->orderBy('libelle')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:zones',
            'libelle' => 'required|string|max:100',
            'ville' => 'nullable|string|max:50',
            'actif' => 'boolean',
        ]);

        $validated['actif'] = $validated['actif'] ?? true;
        $zone = Zone::create($validated);
        return response()->json($zone, 201);
    }

    public function show(Zone $zone): JsonResponse
    {
        return response()->json($zone);
    }

    public function update(Request $request, Zone $zone): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:10|unique:zones,code,' . $zone->id,
            'libelle' => 'sometimes|string|max:100',
            'ville' => 'nullable|string|max:50',
            'actif' => 'sometimes|boolean',
        ]);

        $zone->update($validated);
        return response()->json($zone);
    }

    public function destroy(Zone $zone): JsonResponse
    {
        $zone->delete();
        return response()->json(['message' => 'Zone supprimée']);
    }

    public function liste(): JsonResponse
    {
        return response()->json(
            Zone::where('actif', true)
                ->select('id', 'code', 'libelle')
                ->orderBy('libelle')
                ->get()
        );
    }
}
