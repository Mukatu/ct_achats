<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Service::with('direction')->withCount('users')->orderBy('libelle')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'direction_id' => 'required|uuid|exists:directions,id',
            'code' => 'required|string|max:20|unique:services',
            'libelle' => 'required|string|max:100',
            'actif' => 'boolean',
        ]);

        $validated['actif'] = $validated['actif'] ?? true;
        $service = Service::create($validated);
        return response()->json($service->load('direction'), 201);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json($service->load('direction.zone'));
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $validated = $request->validate([
            'direction_id' => 'sometimes|uuid|exists:directions,id',
            'code' => 'sometimes|string|max:20|unique:services,code,' . $service->id,
            'libelle' => 'sometimes|string|max:100',
            'actif' => 'sometimes|boolean',
        ]);

        $service->update($validated);
        return response()->json($service->load('direction.zone'));
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();
        return response()->json(['message' => 'Service supprimé']);
    }

    public function liste(): JsonResponse
    {
        return response()->json(
            Service::where('actif', true)
                ->select('id', 'code', 'libelle', 'direction_id')
                ->orderBy('libelle')
                ->get()
        );
    }
}
