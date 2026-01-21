<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Direction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DirectionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Direction::with('zone')->withCount('services')->orderBy('libelle')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'required|uuid|exists:zones,id',
            'code' => 'required|string|max:10|unique:directions',
            'libelle' => 'required|string|max:100',
            'libelle_court' => 'nullable|string|max:20',
            'actif' => 'boolean',
        ]);

        $validated['actif'] = $validated['actif'] ?? true;
        $direction = Direction::create($validated);
        return response()->json($direction->load('zone'), 201);
    }

    public function show(Direction $direction): JsonResponse
    {
        return response()->json($direction->load('zone'));
    }

    public function update(Request $request, Direction $direction): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'sometimes|uuid|exists:zones,id',
            'code' => 'sometimes|string|max:10|unique:directions,code,' . $direction->id,
            'libelle' => 'sometimes|string|max:100',
            'libelle_court' => 'nullable|string|max:20',
            'actif' => 'sometimes|boolean',
        ]);

        $direction->update($validated);
        return response()->json($direction->load('zone'));
    }

    public function destroy(Direction $direction): JsonResponse
    {
        $direction->delete();
        return response()->json(['message' => 'Direction supprimée']);
    }

    public function liste(): JsonResponse
    {
        return response()->json(
            Direction::where('actif', true)
                ->select('id', 'code', 'libelle', 'zone_id')
                ->orderBy('libelle')
                ->get()
        );
    }
}
