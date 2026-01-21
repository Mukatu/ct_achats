<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fournisseur;
use App\Models\Societe;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FournisseurController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Fournisseur::query()->orderBy('raison_sociale');

        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('type_fournisseur')) {
            $query->where('type_fournisseur', $request->type_fournisseur);
        }

        if ($request->has('ville')) {
            $query->where('ville', 'like', "%{$request->ville}%");
        }

        if ($request->has('pays')) {
            $query->where('pays', 'like', "%{$request->pays}%");
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('raison_sociale', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('sigle', 'like', "%{$search}%")
                    ->orWhere('niu', 'like', "%{$search}%")
                    ->orWhere('rccm', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'raison_sociale' => 'required|string|max:200',
            'sigle' => 'nullable|string|max:20',
            'niu' => 'nullable|string|max:20',
            'rccm' => 'nullable|string|max:50',
            'adresse' => 'nullable|string|max:500',
            'ville' => 'nullable|string|max:50',
            'pays' => 'nullable|string|max:100',
            'telephone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'site_web' => 'nullable|url|max:200',
            'type_fournisseur' => 'required|in:LOCAL,CEMAC,INTERNATIONAL',
            'statut' => 'nullable|in:PROSPECT,EN_VALIDATION,ACTIF,SUSPENDU,BLOQUE,INACTIF',
            'devise_defaut' => 'nullable|string|max:3',
            'taux_tva' => 'nullable|numeric|min:0|max:100',
            'compte_bancaire' => 'nullable|string|max:50',
            'banque' => 'nullable|string|max:100',
            'rib' => 'nullable|string|max:50',
            'commentaire' => 'nullable|string',
        ]);

        // Valeurs par défaut
        $validated['pays'] = $validated['pays'] ?? 'Congo';
        $validated['statut'] = $validated['statut'] ?? 'PROSPECT';
        $validated['devise_defaut'] = $validated['devise_defaut'] ?? 'XAF';
        $validated['taux_tva'] = $validated['taux_tva'] ?? 18.00;

        // Récupérer la société (depuis l'utilisateur ou la première disponible)
        $user = Auth::user();
        $societeId = $user->service?->direction?->zone?->societe_id ?? Societe::first()?->id;
        $validated['societe_id'] = $societeId;
        $validated['created_by'] = $user->id;

        // Générer le code automatiquement
        $lastCode = Fournisseur::where('code', 'like', 'FOURN%')->orderBy('code', 'desc')->value('code');
        $nextNum = $lastCode ? (int)substr($lastCode, 5) + 1 : 1;
        $validated['code'] = 'FOURN' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $fournisseur = Fournisseur::create($validated);
        return response()->json($fournisseur, 201);
    }

    public function show(Fournisseur $fournisseur): JsonResponse
    {
        return response()->json($fournisseur);
    }

    public function update(Request $request, Fournisseur $fournisseur): JsonResponse
    {
        $validated = $request->validate([
            'raison_sociale' => 'sometimes|string|max:200',
            'sigle' => 'nullable|string|max:20',
            'niu' => 'nullable|string|max:20',
            'rccm' => 'nullable|string|max:50',
            'adresse' => 'nullable|string|max:500',
            'ville' => 'nullable|string|max:50',
            'pays' => 'nullable|string|max:100',
            'telephone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'site_web' => 'nullable|string|max:200',
            'type_fournisseur' => 'sometimes|in:LOCAL,CEMAC,INTERNATIONAL',
            'statut' => 'sometimes|in:PROSPECT,EN_VALIDATION,ACTIF,SUSPENDU,BLOQUE,INACTIF',
            'devise_defaut' => 'nullable|string|max:3',
            'taux_tva' => 'nullable|numeric|min:0|max:100',
            'compte_bancaire' => 'nullable|string|max:50',
            'banque' => 'nullable|string|max:100',
            'rib' => 'nullable|string|max:50',
            'commentaire' => 'nullable|string',
        ]);

        $fournisseur->update($validated);
        return response()->json($fournisseur);
    }

    public function destroy(Fournisseur $fournisseur): JsonResponse
    {
        // Supprimer les contacts et documents associés
        $fournisseur->contacts()->forceDelete();
        $fournisseur->documents()->forceDelete();

        $fournisseur->forceDelete();
        return response()->json(['message' => 'Fournisseur supprimé définitivement']);
    }

    public function liste(): JsonResponse
    {
        return response()->json(
            Fournisseur::where('statut', 'ACTIF')
                ->select('id', 'code', 'raison_sociale')
                ->orderBy('raison_sociale')
                ->get()
                ->map(fn($f) => ['value' => $f->id, 'label' => "{$f->code} - {$f->raison_sociale}"])
        );
    }

    public function contacts(Fournisseur $fournisseur): JsonResponse
    {
        return response()->json($fournisseur->contacts);
    }

    public function storeContact(Request $request, Fournisseur $fournisseur): JsonResponse
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'nullable|string|max:100',
            'fonction' => 'nullable|string|max:100',
            'email' => 'nullable|email',
            'telephone' => 'nullable|string|max:20',
        ]);

        $contact = $fournisseur->contacts()->create($validated);
        return response()->json($contact, 201);
    }

    public function documents(Fournisseur $fournisseur): JsonResponse
    {
        return response()->json($fournisseur->documents);
    }

    public function storeDocument(Request $request, Fournisseur $fournisseur): JsonResponse
    {
        $validated = $request->validate([
            'type_document' => 'required|string|max:50',
            'numero' => 'nullable|string|max:100',
            'date_emission' => 'nullable|date',
            'date_expiration' => 'nullable|date',
            'fichier_url' => 'nullable|string|max:500',
        ]);

        $document = $fournisseur->documents()->create($validated);
        return response()->json($document, 201);
    }
}
