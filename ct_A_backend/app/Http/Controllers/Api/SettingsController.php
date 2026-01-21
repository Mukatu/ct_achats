<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Get general settings
     */
    public function general(): JsonResponse
    {
        $settings = Setting::whereIn('key', [
            'nom_entreprise',
            'adresse',
            'telephone',
            'email',
            'site_web',
            'devise',
            'exercice_debut',
            'exercice_fin',
            'logo_url',
        ])->pluck('value', 'key');

        return response()->json($settings);
    }

    /**
     * Update general settings
     */
    public function updateGeneral(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom_entreprise' => 'nullable|string|max:255',
            'adresse' => 'nullable|string',
            'telephone' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'site_web' => 'nullable|url',
            'devise' => 'nullable|string|max:10',
            'exercice_debut' => 'nullable|date',
            'exercice_fin' => 'nullable|date',
            'logo_url' => 'nullable|string|max:500',
        ]);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json(['message' => 'Paramètres enregistrés']);
    }

    /**
     * Upload company logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,gif,webp,svg|max:2048',
        ]);

        // Supprimer l'ancien logo s'il existe
        $oldLogo = Setting::where('key', 'logo_url')->first();
        if ($oldLogo && $oldLogo->value) {
            $oldPath = str_replace('/storage/', '', $oldLogo->value);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Stocker le nouveau logo
        $file = $request->file('logo');
        $filename = 'logo_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('logos', $filename, 'public');

        $logoUrl = '/storage/' . $path;

        // Sauvegarder dans les paramètres
        Setting::updateOrCreate(
            ['key' => 'logo_url'],
            ['value' => $logoUrl]
        );

        return response()->json([
            'message' => 'Logo uploadé avec succès',
            'url' => $logoUrl,
            'logo_url' => $logoUrl,
        ]);
    }

    /**
     * Delete company logo
     */
    public function deleteLogo(): JsonResponse
    {
        $setting = Setting::where('key', 'logo_url')->first();

        if ($setting && $setting->value) {
            $path = str_replace('/storage/', '', $setting->value);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            $setting->update(['value' => null]);
        }

        return response()->json(['message' => 'Logo supprimé']);
    }

    /**
     * Get seuils settings
     */
    public function seuils(): JsonResponse
    {
        $settings = Setting::whereIn('key', [
            'seuil_chef_service',
            'seuil_directeur',
            'seuil_dg',
            'delai_validation_eb',
            'delai_validation_da',
            'delai_validation_bc',
        ])->pluck('value', 'key');

        // Convertir en nombres
        $result = [];
        foreach ($settings as $key => $value) {
            $result[$key] = is_numeric($value) ? (float) $value : $value;
        }

        return response()->json($result);
    }

    /**
     * Update seuils settings
     */
    public function updateSeuils(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seuil_chef_service' => 'nullable|numeric|min:0',
            'seuil_directeur' => 'nullable|numeric|min:0',
            'seuil_dg' => 'nullable|numeric|min:0',
            'delai_validation_eb' => 'nullable|integer|min:1',
            'delai_validation_da' => 'nullable|integer|min:1',
            'delai_validation_bc' => 'nullable|integer|min:1',
        ]);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json(['message' => 'Seuils enregistrés']);
    }

    /**
     * Get numerotation settings
     */
    public function numerotation(): JsonResponse
    {
        $settings = Setting::whereIn('key', [
            'prefixe_eb',
            'prefixe_da',
            'prefixe_bc',
            'prefixe_br',
            'prefixe_facture',
            'longueur_numero',
            'compteur_eb',
            'compteur_da',
            'compteur_bc',
            'compteur_br',
            'compteur_facture',
            'reinitialiser_annuellement',
        ])->pluck('value', 'key');

        // Valeurs par défaut et conversion
        $defaults = [
            'prefixe_eb' => 'EB',
            'prefixe_da' => 'DA',
            'prefixe_bc' => 'BC',
            'prefixe_br' => 'BR',
            'prefixe_facture' => 'FAC',
            'longueur_numero' => 5,
            'compteur_eb' => 1,
            'compteur_da' => 1,
            'compteur_bc' => 1,
            'compteur_br' => 1,
            'compteur_facture' => 1,
            'reinitialiser_annuellement' => true,
        ];

        $result = [];
        foreach ($defaults as $key => $default) {
            $value = $settings[$key] ?? $default;
            if (in_array($key, ['longueur_numero', 'compteur_eb', 'compteur_da', 'compteur_bc', 'compteur_br', 'compteur_facture'])) {
                $result[$key] = (int) $value;
            } elseif ($key === 'reinitialiser_annuellement') {
                $result[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } else {
                $result[$key] = $value;
            }
        }

        return response()->json($result);
    }

    /**
     * Update numerotation settings
     */
    public function updateNumerotation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prefixe_eb' => 'nullable|string|max:10',
            'prefixe_da' => 'nullable|string|max:10',
            'prefixe_bc' => 'nullable|string|max:10',
            'prefixe_br' => 'nullable|string|max:10',
            'prefixe_facture' => 'nullable|string|max:10',
            'longueur_numero' => 'nullable|integer|min:4|max:10',
            'compteur_eb' => 'nullable|integer|min:1',
            'compteur_da' => 'nullable|integer|min:1',
            'compteur_bc' => 'nullable|integer|min:1',
            'compteur_br' => 'nullable|integer|min:1',
            'compteur_facture' => 'nullable|integer|min:1',
            'reinitialiser_annuellement' => 'nullable|boolean',
        ]);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
            );
        }

        return response()->json(['message' => 'Numérotation enregistrée']);
    }
}
