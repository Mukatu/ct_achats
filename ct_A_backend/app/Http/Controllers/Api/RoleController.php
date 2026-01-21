<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::withCount('users')->orderBy('libelle')->get();
        return response()->json($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:roles',
            'libelle' => 'required|string|max:100',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'actif' => 'boolean',
        ]);

        $validated['actif'] = $validated['actif'] ?? true;
        $validated['permissions'] = $validated['permissions'] ?? [];
        $role = Role::create($validated);

        return response()->json($role, 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json($role->loadCount('users'));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:20|unique:roles,code,' . $role->id,
            'libelle' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'actif' => 'sometimes|boolean',
        ]);

        $role->update($validated);
        return response()->json($role);
    }

    public function destroy(Role $role): JsonResponse
    {
        // Vérifier si le rôle est utilisé
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Ce rôle est assigné à des utilisateurs et ne peut pas être supprimé'
            ], 422);
        }

        $role->delete();
        return response()->json(['message' => 'Rôle supprimé']);
    }
}
