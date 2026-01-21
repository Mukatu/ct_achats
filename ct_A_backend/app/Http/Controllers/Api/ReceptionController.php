<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reception;
use App\Models\LigneReception;
use App\Models\BonCommande;
use App\Models\Societe;
use App\Services\NumerotationService;
use App\Enums\StatutBR;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ReceptionController extends Controller
{
    protected NumerotationService $numerotation;

    public function __construct(NumerotationService $numerotation)
    {
        $this->numerotation = $numerotation;
    }

    /**
     * Liste des réceptions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Reception::with(['bonCommande.fournisseur', 'receptionnaire'])
            ->orderBy('created_at', 'desc');

        // Filtres
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('bon_commande_id')) {
            $query->where('bon_commande_id', $request->bon_commande_id);
        }

        if ($request->has('type_reception')) {
            $query->where('type_reception', $request->type_reception);
        }

        if ($request->has('date_debut')) {
            $query->whereDate('date_reception', '>=', $request->date_debut);
        }

        if ($request->has('date_fin')) {
            $query->whereDate('date_reception', '<=', $request->date_fin);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('numero_bl_fournisseur', 'like', "%{$search}%")
                    ->orWhereHas('bonCommande', function ($q2) use ($search) {
                        $q2->where('numero', 'like', "%{$search}%")
                           ->orWhere('objet', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        return response()->json($query->paginate($perPage));
    }

    /**
     * Créer une nouvelle réception
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bon_commande_id' => 'required|uuid|exists:bons_commande,id',
            'date_reception' => 'required|date',
            'type_reception' => 'required|in:LIVRAISON,SERVICE_FAIT,PARTIELLE',
            'lieu_reception' => 'nullable|string|max:200',
            'numero_bl_fournisseur' => 'nullable|string|max:50',
            'date_bl_fournisseur' => 'nullable|date',
            'numero_tracking' => 'nullable|string|max:100',
            'transporteur' => 'nullable|string|max:100',
            'commentaire' => 'nullable|string',
            'lignes' => 'required|array|min:1',
            'lignes.*.ligne_bon_commande_id' => 'required|uuid|exists:lignes_bon_commande,id',
            'lignes.*.quantite_recue' => 'required|numeric|min:0',
            'lignes.*.quantite_conforme' => 'nullable|numeric|min:0',
            'lignes.*.quantite_non_conforme' => 'nullable|numeric|min:0',
            'lignes.*.quantite_refusee' => 'nullable|numeric|min:0',
            'lignes.*.motif_non_conformite' => 'nullable|string',
            'lignes.*.motif_refus' => 'nullable|string',
            'lignes.*.numero_lot' => 'nullable|string|max:50',
            'lignes.*.numero_serie' => 'nullable|string|max:100',
            'lignes.*.commentaire' => 'nullable|string',
        ]);

        $user = Auth::user();
        $societeId = $user->service?->direction?->zone?->societe_id ?? Societe::first()?->id;

        $reception = DB::transaction(function () use ($validated, $user, $societeId) {
            $numero = $this->numerotation->genererNumero($societeId, 'BR');

            $reception = Reception::create([
                'societe_id' => $societeId,
                'numero' => $numero,
                'bon_commande_id' => $validated['bon_commande_id'],
                'date_reception' => $validated['date_reception'],
                'receptionnaire_id' => $user->id,
                'type_reception' => $validated['type_reception'],
                'lieu_reception' => $validated['lieu_reception'] ?? null,
                'numero_bl_fournisseur' => $validated['numero_bl_fournisseur'] ?? null,
                'date_bl_fournisseur' => $validated['date_bl_fournisseur'] ?? null,
                'numero_tracking' => $validated['numero_tracking'] ?? null,
                'transporteur' => $validated['transporteur'] ?? null,
                'commentaire' => $validated['commentaire'] ?? null,
                'statut' => StatutBR::BROUILLON,
                'created_by' => $user->id,
            ]);

            // Créer les lignes
            foreach ($validated['lignes'] as $index => $ligneData) {
                // Récupérer la quantité attendue depuis la ligne BC
                $ligneBC = \App\Models\LigneBonCommande::find($ligneData['ligne_bon_commande_id']);
                $quantiteAttendue = $ligneBC ? ($ligneBC->quantite - $ligneBC->quantite_recue) : 0;

                $quantiteConforme = $ligneData['quantite_conforme'] ?? $ligneData['quantite_recue'];

                LigneReception::create([
                    'reception_id' => $reception->id,
                    'ligne_bon_commande_id' => $ligneData['ligne_bon_commande_id'],
                    'numero_ligne' => $index + 1,
                    'quantite_attendue' => $quantiteAttendue,
                    'quantite_recue' => $ligneData['quantite_recue'],
                    'quantite_conforme' => $quantiteConforme,
                    'quantite_non_conforme' => $ligneData['quantite_non_conforme'] ?? 0,
                    'quantite_refusee' => $ligneData['quantite_refusee'] ?? 0,
                    'motif_non_conformite' => $ligneData['motif_non_conformite'] ?? null,
                    'motif_refus' => $ligneData['motif_refus'] ?? null,
                    'numero_lot' => $ligneData['numero_lot'] ?? null,
                    'numero_serie' => $ligneData['numero_serie'] ?? null,
                    'commentaire' => $ligneData['commentaire'] ?? null,
                ]);
            }

            return $reception;
        });

        return response()->json([
            'message' => 'Réception créée avec succès',
            'data' => $reception->load(['bonCommande.fournisseur', 'receptionnaire', 'lignes.ligneBonCommande']),
        ], 201);
    }

    /**
     * Afficher une réception
     */
    public function show(string $id): JsonResponse
    {
        $reception = Reception::with([
            'bonCommande.fournisseur',
            'bonCommande.lignes.uniteMesure',
            'receptionnaire',
            'createdBy',
            'lignes.ligneBonCommande.uniteMesure',
        ])->findOrFail($id);

        return response()->json($reception);
    }

    /**
     * Mettre à jour une réception
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $reception = Reception::findOrFail($id);

        if (!$reception->statut->canEdit()) {
            return response()->json([
                'message' => 'Cette réception ne peut plus être modifiée'
            ], 422);
        }

        $validated = $request->validate([
            'date_reception' => 'sometimes|date',
            'type_reception' => 'sometimes|in:LIVRAISON,SERVICE_FAIT,PARTIELLE',
            'lieu_reception' => 'nullable|string|max:200',
            'numero_bl_fournisseur' => 'nullable|string|max:50',
            'date_bl_fournisseur' => 'nullable|date',
            'numero_tracking' => 'nullable|string|max:100',
            'transporteur' => 'nullable|string|max:100',
            'commentaire' => 'nullable|string',
            'lignes' => 'sometimes|array',
            'lignes.*.id' => 'nullable|uuid',
            'lignes.*.ligne_bon_commande_id' => 'required|uuid|exists:lignes_bon_commande,id',
            'lignes.*.quantite_recue' => 'required|numeric|min:0',
            'lignes.*.quantite_conforme' => 'nullable|numeric|min:0',
            'lignes.*.quantite_non_conforme' => 'nullable|numeric|min:0',
            'lignes.*.quantite_refusee' => 'nullable|numeric|min:0',
            'lignes.*.motif_non_conformite' => 'nullable|string',
            'lignes.*.motif_refus' => 'nullable|string',
            'lignes.*.numero_lot' => 'nullable|string|max:50',
            'lignes.*.numero_serie' => 'nullable|string|max:100',
            'lignes.*.commentaire' => 'nullable|string',
        ]);

        DB::transaction(function () use ($reception, $validated) {
            $reception->update([
                'date_reception' => $validated['date_reception'] ?? $reception->date_reception,
                'type_reception' => $validated['type_reception'] ?? $reception->type_reception,
                'lieu_reception' => $validated['lieu_reception'] ?? $reception->lieu_reception,
                'numero_bl_fournisseur' => $validated['numero_bl_fournisseur'] ?? $reception->numero_bl_fournisseur,
                'date_bl_fournisseur' => $validated['date_bl_fournisseur'] ?? $reception->date_bl_fournisseur,
                'numero_tracking' => $validated['numero_tracking'] ?? $reception->numero_tracking,
                'transporteur' => $validated['transporteur'] ?? $reception->transporteur,
                'commentaire' => $validated['commentaire'] ?? $reception->commentaire,
            ]);

            // Mettre à jour les lignes si fournies
            if (isset($validated['lignes'])) {
                $existingIds = [];

                foreach ($validated['lignes'] as $index => $ligneData) {
                    $ligneBC = \App\Models\LigneBonCommande::find($ligneData['ligne_bon_commande_id']);
                    $quantiteAttendue = $ligneBC ? ($ligneBC->quantite - $ligneBC->quantite_recue) : 0;
                    $quantiteConforme = $ligneData['quantite_conforme'] ?? $ligneData['quantite_recue'];

                    if (!empty($ligneData['id'])) {
                        $ligne = LigneReception::find($ligneData['id']);
                        if ($ligne && $ligne->reception_id === $reception->id) {
                            $ligne->update([
                                'quantite_recue' => $ligneData['quantite_recue'],
                                'quantite_conforme' => $quantiteConforme,
                                'quantite_non_conforme' => $ligneData['quantite_non_conforme'] ?? 0,
                                'quantite_refusee' => $ligneData['quantite_refusee'] ?? 0,
                                'motif_non_conformite' => $ligneData['motif_non_conformite'] ?? null,
                                'motif_refus' => $ligneData['motif_refus'] ?? null,
                                'numero_lot' => $ligneData['numero_lot'] ?? null,
                                'numero_serie' => $ligneData['numero_serie'] ?? null,
                                'commentaire' => $ligneData['commentaire'] ?? null,
                            ]);
                            $existingIds[] = $ligne->id;
                        }
                    } else {
                        $ligne = LigneReception::create([
                            'reception_id' => $reception->id,
                            'ligne_bon_commande_id' => $ligneData['ligne_bon_commande_id'],
                            'numero_ligne' => $index + 1,
                            'quantite_attendue' => $quantiteAttendue,
                            'quantite_recue' => $ligneData['quantite_recue'],
                            'quantite_conforme' => $quantiteConforme,
                            'quantite_non_conforme' => $ligneData['quantite_non_conforme'] ?? 0,
                            'quantite_refusee' => $ligneData['quantite_refusee'] ?? 0,
                            'motif_non_conformite' => $ligneData['motif_non_conformite'] ?? null,
                            'motif_refus' => $ligneData['motif_refus'] ?? null,
                            'numero_lot' => $ligneData['numero_lot'] ?? null,
                            'numero_serie' => $ligneData['numero_serie'] ?? null,
                            'commentaire' => $ligneData['commentaire'] ?? null,
                        ]);
                        $existingIds[] = $ligne->id;
                    }
                }

                // Supprimer les lignes qui ne sont plus présentes
                LigneReception::where('reception_id', $reception->id)
                    ->whereNotIn('id', $existingIds)
                    ->delete();
            }
        });

        return response()->json([
            'message' => 'Réception mise à jour',
            'data' => $reception->fresh(['bonCommande.fournisseur', 'receptionnaire', 'lignes.ligneBonCommande']),
        ]);
    }

    /**
     * Supprimer une réception
     */
    public function destroy(string $id): JsonResponse
    {
        $reception = Reception::findOrFail($id);

        if (!$reception->statut->canCancel()) {
            return response()->json([
                'message' => 'Cette réception ne peut pas être supprimée'
            ], 422);
        }

        // Supprimer les lignes de réception
        $reception->lignes()->forceDelete();

        $reception->forceDelete();

        return response()->json([
            'message' => 'Réception supprimée définitivement'
        ]);
    }

    /**
     * Valider une réception
     */
    public function valider(Request $request, string $id): JsonResponse
    {
        $reception = Reception::with('lignes.ligneBonCommande')->findOrFail($id);

        if (!$reception->statut->canValidate()) {
            return response()->json([
                'message' => 'Cette réception ne peut pas être validée'
            ], 422);
        }

        DB::transaction(function () use ($reception) {
            // Mettre à jour le statut de la réception
            $reception->update(['statut' => StatutBR::VALIDEE]);

            // Mettre à jour les quantités reçues sur les lignes BC
            foreach ($reception->lignes as $ligneReception) {
                $ligneBC = $ligneReception->ligneBonCommande;
                if ($ligneBC) {
                    $nouvelleQteRecue = $ligneBC->quantite_recue + $ligneReception->quantite_conforme;
                    $ligneBC->update([
                        'quantite_recue' => $nouvelleQteRecue,
                        'statut_ligne' => $nouvelleQteRecue >= $ligneBC->quantite ? 'RECU_TOTAL' : 'RECU_PARTIEL',
                    ]);
                }
            }

            // Vérifier si toutes les lignes BC sont complètes
            $bonCommande = $reception->bonCommande;
            $toutRecu = $bonCommande->lignes->every(fn($l) => $l->quantite_recue >= $l->quantite);

            if ($toutRecu) {
                $bonCommande->update(['statut' => 'LIVRE']);
            } elseif ($bonCommande->lignes->some(fn($l) => $l->quantite_recue > 0)) {
                $bonCommande->update(['statut' => 'LIVRAISON_PARTIELLE']);
            }
        });

        return response()->json([
            'message' => 'Réception validée avec succès',
            'data' => $reception->fresh(['bonCommande', 'lignes']),
        ]);
    }

    /**
     * Liste des statuts
     */
    public function listeStatuts(): JsonResponse
    {
        $statuts = collect(StatutBR::cases())->map(fn($s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ]);

        return response()->json($statuts);
    }

    /**
     * Récupérer les lignes d'un BC pour création de réception
     */
    public function lignesBonCommande(string $bonCommandeId): JsonResponse
    {
        $bc = BonCommande::with(['lignes.uniteMesure', 'fournisseur'])->findOrFail($bonCommandeId);

        $lignesDisponibles = $bc->lignes->map(function ($ligne) {
            return [
                'id' => $ligne->id,
                'numero_ligne' => $ligne->numero_ligne,
                'designation' => $ligne->designation,
                'quantite_commandee' => $ligne->quantite,
                'quantite_deja_recue' => $ligne->quantite_recue,
                'quantite_restante' => max(0, $ligne->quantite - $ligne->quantite_recue),
                'unite' => $ligne->uniteMesure?->code,
                'prix_unitaire' => $ligne->prix_unitaire_xaf,
            ];
        })->filter(fn($l) => $l['quantite_restante'] > 0)->values();

        return response()->json([
            'bon_commande' => [
                'id' => $bc->id,
                'numero' => $bc->numero,
                'objet' => $bc->objet,
                'fournisseur' => $bc->fournisseur->raison_sociale ?? null,
                'date_bc' => $bc->date_bc,
            ],
            'lignes' => $lignesDisponibles,
        ]);
    }
}
