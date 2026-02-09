<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['service.direction.zone', 'roles']);

        // Filtre par recherche
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        // Filtre par acheteur
        if ($request->filled('est_acheteur')) {
            $query->where('est_acheteur', $request->boolean('est_acheteur'));
        }

        // Filtre par valideur
        if ($request->filled('est_valideur')) {
            $query->where('est_valideur', $request->boolean('est_valideur'));
        }

        // Filtre par actif
        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        return response()->json($query->orderBy('nom')->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'matricule' => 'nullable|string|max:20|unique:users',
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'telephone' => 'nullable|string|max:20',
            'service_id' => 'nullable|uuid|exists:services,id',
            'est_acheteur' => 'boolean',
            'est_valideur' => 'boolean',
            'seuil_validation' => 'nullable|numeric|min:0',
            'actif' => 'boolean',
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'uuid|exists:roles,id',
        ]);

        // Gérer les rôles séparément
        $roleIds = $validated['role_ids'] ?? [];
        unset($validated['role_ids']);

        $validated['password'] = Hash::make($validated['password']);
        $validated['actif'] = $validated['actif'] ?? true;
        $user = User::create($validated);

        // Attacher les rôles si fournis
        if (!empty($roleIds)) {
            $user->roles()->attach($roleIds);
        }

        return response()->json($user->load(['service.direction.zone', 'roles']), 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load(['service.direction.zone', 'roles', 'manager']));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'matricule' => 'nullable|string|max:20|unique:users,matricule,' . $user->id,
            'nom' => 'sometimes|string|max:100',
            'prenom' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'telephone' => 'nullable|string|max:20',
            'service_id' => 'nullable|uuid|exists:services,id',
            'est_acheteur' => 'sometimes|boolean',
            'est_valideur' => 'sometimes|boolean',
            'seuil_validation' => 'nullable|numeric|min:0',
            'actif' => 'sometimes|boolean',
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'uuid|exists:roles,id',
        ]);

        // Hash password si fourni
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Gérer les rôles séparément
        $roleIds = $validated['role_ids'] ?? null;
        unset($validated['role_ids']);

        $user->update($validated);

        // Synchroniser les rôles si fournis
        if ($roleIds !== null) {
            $user->roles()->sync($roleIds);
        }

        return response()->json($user->load(['service.direction.zone', 'roles']));
    }

    public function destroy(User $user, Request $request): JsonResponse
    {
        // Si force=true, suppression définitive (soft delete)
        if ($request->boolean('force')) {
            $user->delete();
            return response()->json(['message' => 'Utilisateur supprimé']);
        }

        // Sinon, simple désactivation
        $user->update(['actif' => false]);
        return response()->json(['message' => 'Utilisateur désactivé']);
    }

    public function acheteurs(): JsonResponse
    {
        return response()->json(
            User::where('est_acheteur', true)
                ->where('actif', true)
                ->select('id', 'nom', 'prenom', 'email')
                ->get()
        );
    }

    public function valideurs(): JsonResponse
    {
        return response()->json(
            User::where('est_valideur', true)
                ->where('actif', true)
                ->select('id', 'nom', 'prenom', 'email', 'seuil_validation')
                ->get()
        );
    }

    public function listeAcheteurs(): JsonResponse
    {
        return response()->json(
            User::where('est_acheteur', true)
                ->where('actif', true)
                ->get()
                ->map(fn($u) => ['value' => $u->id, 'label' => $u->nom_complet])
        );
    }
}
