<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Connexion utilisateur
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        if (!$user->actif) {
            throw ValidationException::withMessages([
                'email' => ['Ce compte est désactivé.'],
            ]);
        }

        // Mettre à jour la dernière connexion
        $user->update(['derniere_connexion' => now()]);

        // Créer le token
        $deviceName = $request->device_name ?? 'CT_Achats';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user' => $user->load(['service.direction.zone', 'roles']),
            'token' => $token,
        ]);
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request): JsonResponse
    {
        // Révoquer le token actuel
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }

    /**
     * Informations utilisateur connecté
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'service.direction.zone',
            'roles',
            'manager'
        ]);

        return response()->json([
            'user' => $user,
            'permissions' => $this->getUserPermissions($user),
        ]);
    }

    /**
     * Mise à jour du profil
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:100',
            'prenom' => 'sometimes|string|max:100',
            'telephone' => 'nullable|string|max:20',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profil mis à jour',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Changement de mot de passe
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Mot de passe modifié avec succès'
        ]);
    }

    /**
     * Récupérer toutes les permissions de l'utilisateur
     */
    protected function getUserPermissions(User $user): array
    {
        $permissions = [];

        foreach ($user->roles as $role) {
            $rolePermissions = $role->permissions ?? [];
            if (in_array('all', $rolePermissions)) {
                return ['all'];
            }
            $permissions = array_merge($permissions, $rolePermissions);
        }

        return array_unique($permissions);
    }
}
