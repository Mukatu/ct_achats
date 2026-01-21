<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use App\Models\LigneFacture;
use App\Models\Societe;
use App\Services\NumerotationService;
use App\Enums\StatutFacture;
use App\Enums\StatutPaiement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FactureController extends Controller
{
    protected NumerotationService $numerotation;

    public function __construct(NumerotationService $numerotation)
    {
        $this->numerotation = $numerotation;
    }

    /**
     * Liste des factures
     */
    public function index(Request $request): JsonResponse
    {
        $query = Facture::with(['fournisseur', 'bonCommande', 'createdBy'])
            ->orderBy('created_at', 'desc');

        // Filtres
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('statut_paiement')) {
            $query->where('statut_paiement', $request->statut_paiement);
        }

        if ($request->has('type_facture')) {
            $query->where('type_facture', $request->type_facture);
        }

        if ($request->has('fournisseur_id')) {
            $query->where('fournisseur_id', $request->fournisseur_id);
        }

        if ($request->has('bon_commande_id')) {
            $query->where('bon_commande_id', $request->bon_commande_id);
        }

        if ($request->has('date_debut')) {
            $query->whereDate('date_facture', '>=', $request->date_debut);
        }

        if ($request->has('date_fin')) {
            $query->whereDate('date_facture', '<=', $request->date_fin);
        }

        if ($request->has('en_retard') && $request->en_retard) {
            $query->where('date_echeance', '<', now())
                  ->whereNotIn('statut_paiement', ['PAYEE']);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero_interne', 'like', "%{$search}%")
                    ->orWhere('numero_fournisseur', 'like', "%{$search}%")
                    ->orWhereHas('fournisseur', function ($q2) use ($search) {
                        $q2->where('raison_sociale', 'like', "%{$search}%");
                    })
                    ->orWhereHas('bonCommande', function ($q2) use ($search) {
                        $q2->where('numero', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        return response()->json($query->paginate($perPage));
    }

    /**
     * Creer une nouvelle facture
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fournisseur_id' => 'required|uuid|exists:fournisseurs,id',
            'bon_commande_id' => 'nullable|uuid|exists:bons_commande,id',
            'type_facture' => 'required|in:FACTURE,AVOIR,ACOMPTE,SITUATION',
            'numero_fournisseur' => 'required|string|max:50',
            'date_facture' => 'required|date',
            'date_reception' => 'required|date',
            'date_echeance' => 'required|date|after_or_equal:date_facture',
            'montant_ht' => 'required|numeric|min:0',
            'taux_tva' => 'required|numeric|min:0|max:100',
            'retenue_source' => 'nullable|numeric|min:0',
            'commentaire' => 'nullable|string',
            'lignes' => 'required|array|min:1',
            'lignes.*.designation' => 'required|string|max:500',
            'lignes.*.quantite' => 'required|numeric|min:0',
            'lignes.*.prix_unitaire_ht' => 'required|numeric|min:0',
            'lignes.*.taux_tva' => 'nullable|numeric|min:0|max:100',
            'lignes.*.remise_percent' => 'nullable|numeric|min:0|max:100',
            'lignes.*.ligne_bon_commande_id' => 'nullable|uuid',
            'lignes.*.ligne_reception_id' => 'nullable|uuid',
            'lignes.*.reference_fournisseur' => 'nullable|string|max:50',
            'lignes.*.commentaire' => 'nullable|string',
        ]);

        $user = Auth::user();
        $societeId = $user->service?->direction?->zone?->societe_id ?? Societe::first()?->id;

        $facture = DB::transaction(function () use ($validated, $user, $societeId) {
            $numero = $this->numerotation->genererNumero($societeId, 'FACT');

            // Calcul des montants
            $montantHT = $validated['montant_ht'];
            $tauxTVA = $validated['taux_tva'];
            $montantTVA = round($montantHT * $tauxTVA / 100);
            $montantTTC = $montantHT + $montantTVA;
            $retenueSource = $validated['retenue_source'] ?? 0;
            $netAPayer = $montantTTC - $retenueSource;

            $facture = Facture::create([
                'societe_id' => $societeId,
                'numero_interne' => $numero,
                'numero_fournisseur' => $validated['numero_fournisseur'],
                'fournisseur_id' => $validated['fournisseur_id'],
                'bon_commande_id' => $validated['bon_commande_id'] ?? null,
                'type_facture' => $validated['type_facture'],
                'date_facture' => $validated['date_facture'],
                'date_reception' => $validated['date_reception'],
                'date_echeance' => $validated['date_echeance'],
                'montant_ht' => $montantHT,
                'taux_tva' => $tauxTVA,
                'montant_tva' => $montantTVA,
                'montant_ttc' => $montantTTC,
                'retenue_source' => $retenueSource,
                'net_a_payer' => $netAPayer,
                'devise' => 'XAF',
                'statut' => StatutFacture::BROUILLON,
                'statut_paiement' => StatutPaiement::NON_PAYEE,
                'commentaire' => $validated['commentaire'] ?? null,
                'created_by' => $user->id,
            ]);

            // Creer les lignes
            foreach ($validated['lignes'] as $index => $ligneData) {
                $qte = $ligneData['quantite'];
                $prixUnit = $ligneData['prix_unitaire_ht'];
                $remise = $ligneData['remise_percent'] ?? 0;
                $tauxTvaLigne = $ligneData['taux_tva'] ?? $tauxTVA;

                $montantHTLigne = $qte * $prixUnit * (1 - $remise / 100);
                $montantTVALigne = round($montantHTLigne * $tauxTvaLigne / 100);
                $montantTTCLigne = $montantHTLigne + $montantTVALigne;

                LigneFacture::create([
                    'facture_id' => $facture->id,
                    'numero_ligne' => $index + 1,
                    'ligne_bon_commande_id' => $ligneData['ligne_bon_commande_id'] ?? null,
                    'ligne_reception_id' => $ligneData['ligne_reception_id'] ?? null,
                    'reference_fournisseur' => $ligneData['reference_fournisseur'] ?? null,
                    'designation' => $ligneData['designation'],
                    'quantite' => $qte,
                    'prix_unitaire_ht' => $prixUnit,
                    'remise_percent' => $remise,
                    'montant_ht' => $montantHTLigne,
                    'taux_tva' => $tauxTvaLigne,
                    'montant_tva' => $montantTVALigne,
                    'montant_ttc' => $montantTTCLigne,
                    'statut_rapprochement' => 'NON_RAPPROCHEE',
                    'commentaire' => $ligneData['commentaire'] ?? null,
                ]);
            }

            return $facture;
        });

        return response()->json([
            'message' => 'Facture creee avec succes',
            'data' => $facture->load(['fournisseur', 'bonCommande', 'lignes']),
        ], 201);
    }

    /**
     * Afficher une facture
     */
    public function show(string $id): JsonResponse
    {
        $facture = Facture::with([
            'fournisseur',
            'bonCommande.lignes',
            'valideur',
            'createdBy',
            'lignes.ligneBonCommande',
            'lignes.ligneReception',
            'lignes.uniteMesure',
        ])->findOrFail($id);

        return response()->json($facture);
    }

    /**
     * Mettre a jour une facture
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $facture = Facture::findOrFail($id);

        if (!$facture->statut->canEdit()) {
            return response()->json([
                'message' => 'Cette facture ne peut plus etre modifiee'
            ], 422);
        }

        $validated = $request->validate([
            'numero_fournisseur' => 'sometimes|string|max:50',
            'type_facture' => 'sometimes|in:FACTURE,AVOIR,ACOMPTE,SITUATION',
            'date_facture' => 'sometimes|date',
            'date_reception' => 'sometimes|date',
            'date_echeance' => 'sometimes|date',
            'montant_ht' => 'sometimes|numeric|min:0',
            'taux_tva' => 'sometimes|numeric|min:0|max:100',
            'retenue_source' => 'nullable|numeric|min:0',
            'commentaire' => 'nullable|string',
            'lignes' => 'sometimes|array',
            'lignes.*.id' => 'nullable|uuid',
            'lignes.*.designation' => 'required|string|max:500',
            'lignes.*.quantite' => 'required|numeric|min:0',
            'lignes.*.prix_unitaire_ht' => 'required|numeric|min:0',
            'lignes.*.taux_tva' => 'nullable|numeric|min:0|max:100',
            'lignes.*.remise_percent' => 'nullable|numeric|min:0|max:100',
            'lignes.*.reference_fournisseur' => 'nullable|string|max:50',
            'lignes.*.commentaire' => 'nullable|string',
        ]);

        DB::transaction(function () use ($facture, $validated) {
            // Recalcul des montants si modifies
            $montantHT = $validated['montant_ht'] ?? $facture->montant_ht;
            $tauxTVA = $validated['taux_tva'] ?? $facture->taux_tva;
            $montantTVA = round($montantHT * $tauxTVA / 100);
            $montantTTC = $montantHT + $montantTVA;
            $retenueSource = $validated['retenue_source'] ?? $facture->retenue_source ?? 0;
            $netAPayer = $montantTTC - $retenueSource;

            $facture->update([
                'numero_fournisseur' => $validated['numero_fournisseur'] ?? $facture->numero_fournisseur,
                'type_facture' => $validated['type_facture'] ?? $facture->type_facture,
                'date_facture' => $validated['date_facture'] ?? $facture->date_facture,
                'date_reception' => $validated['date_reception'] ?? $facture->date_reception,
                'date_echeance' => $validated['date_echeance'] ?? $facture->date_echeance,
                'montant_ht' => $montantHT,
                'taux_tva' => $tauxTVA,
                'montant_tva' => $montantTVA,
                'montant_ttc' => $montantTTC,
                'retenue_source' => $retenueSource,
                'net_a_payer' => $netAPayer,
                'commentaire' => $validated['commentaire'] ?? $facture->commentaire,
            ]);

            // Mettre a jour les lignes si fournies
            if (isset($validated['lignes'])) {
                $existingIds = [];

                foreach ($validated['lignes'] as $index => $ligneData) {
                    $tauxTvaLigne = $ligneData['taux_tva'] ?? $tauxTVA;
                    $qte = $ligneData['quantite'];
                    $prixUnit = $ligneData['prix_unitaire_ht'];
                    $remise = $ligneData['remise_percent'] ?? 0;

                    $montantHTLigne = $qte * $prixUnit * (1 - $remise / 100);
                    $montantTVALigne = round($montantHTLigne * $tauxTvaLigne / 100);
                    $montantTTCLigne = $montantHTLigne + $montantTVALigne;

                    if (!empty($ligneData['id'])) {
                        $ligne = LigneFacture::find($ligneData['id']);
                        if ($ligne && $ligne->facture_id === $facture->id) {
                            $ligne->update([
                                'designation' => $ligneData['designation'],
                                'quantite' => $qte,
                                'prix_unitaire_ht' => $prixUnit,
                                'remise_percent' => $remise,
                                'montant_ht' => $montantHTLigne,
                                'taux_tva' => $tauxTvaLigne,
                                'montant_tva' => $montantTVALigne,
                                'montant_ttc' => $montantTTCLigne,
                                'reference_fournisseur' => $ligneData['reference_fournisseur'] ?? null,
                                'commentaire' => $ligneData['commentaire'] ?? null,
                            ]);
                            $existingIds[] = $ligne->id;
                        }
                    } else {
                        $ligne = LigneFacture::create([
                            'facture_id' => $facture->id,
                            'numero_ligne' => $index + 1,
                            'designation' => $ligneData['designation'],
                            'quantite' => $qte,
                            'prix_unitaire_ht' => $prixUnit,
                            'remise_percent' => $remise,
                            'montant_ht' => $montantHTLigne,
                            'taux_tva' => $tauxTvaLigne,
                            'montant_tva' => $montantTVALigne,
                            'montant_ttc' => $montantTTCLigne,
                            'statut_rapprochement' => 'NON_RAPPROCHEE',
                            'reference_fournisseur' => $ligneData['reference_fournisseur'] ?? null,
                            'commentaire' => $ligneData['commentaire'] ?? null,
                        ]);
                        $existingIds[] = $ligne->id;
                    }
                }

                // Supprimer les lignes qui ne sont plus presentes
                LigneFacture::where('facture_id', $facture->id)
                    ->whereNotIn('id', $existingIds)
                    ->delete();
            }
        });

        return response()->json([
            'message' => 'Facture mise a jour',
            'data' => $facture->fresh(['fournisseur', 'bonCommande', 'lignes']),
        ]);
    }

    /**
     * Supprimer une facture
     */
    public function destroy(string $id): JsonResponse
    {
        $facture = Facture::findOrFail($id);

        if (!$facture->statut->canCancel()) {
            return response()->json([
                'message' => 'Cette facture ne peut pas etre supprimee'
            ], 422);
        }

        // Supprimer les lignes de facture
        $facture->lignes()->forceDelete();

        $facture->forceDelete();

        return response()->json([
            'message' => 'Facture supprimee definitivement'
        ]);
    }

    /**
     * Soumettre une facture pour rapprochement
     */
    public function soumettre(Request $request, string $id): JsonResponse
    {
        $facture = Facture::findOrFail($id);

        if ($facture->statut !== StatutFacture::BROUILLON) {
            return response()->json([
                'message' => 'Seule une facture en brouillon peut etre soumise'
            ], 422);
        }

        $facture->update(['statut' => StatutFacture::A_RAPPROCHER]);

        return response()->json([
            'message' => 'Facture soumise pour rapprochement',
            'data' => $facture->fresh(['fournisseur', 'lignes']),
        ]);
    }

    /**
     * Valider une facture
     */
    public function valider(Request $request, string $id): JsonResponse
    {
        $facture = Facture::findOrFail($id);

        if (!$facture->statut->canValidate()) {
            return response()->json([
                'message' => 'Cette facture ne peut pas etre validee'
            ], 422);
        }

        $facture->update([
            'statut' => StatutFacture::VALIDEE,
            'date_validation' => now(),
            'valideur_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Facture validee avec succes',
            'data' => $facture->fresh(['fournisseur', 'valideur']),
        ]);
    }

    /**
     * Rejeter une facture
     */
    public function rejeter(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'motif' => 'required|string|min:10',
        ]);

        $facture = Facture::findOrFail($id);

        $facture->update([
            'statut' => StatutFacture::REJETEE,
            'commentaire' => $request->motif,
        ]);

        return response()->json([
            'message' => 'Facture rejetee',
            'data' => $facture->fresh(),
        ]);
    }

    /**
     * Enregistrer un paiement
     */
    public function enregistrerPaiement(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'montant' => 'required|numeric|min:0',
            'date_paiement' => 'required|date',
            'reference' => 'nullable|string|max:100',
        ]);

        $facture = Facture::findOrFail($id);

        if ($facture->statut !== StatutFacture::VALIDEE) {
            return response()->json([
                'message' => 'Seule une facture validee peut recevoir un paiement'
            ], 422);
        }

        $nouveauMontantPaye = ($facture->montant_paye ?? 0) + $validated['montant'];
        $nouveauStatut = $nouveauMontantPaye >= $facture->net_a_payer
            ? StatutPaiement::PAYEE
            : StatutPaiement::PARTIEL;

        $facture->update([
            'montant_paye' => $nouveauMontantPaye,
            'date_paiement' => $validated['date_paiement'],
            'reference_paiement' => $validated['reference'] ?? $facture->reference_paiement,
            'statut_paiement' => $nouveauStatut,
        ]);

        return response()->json([
            'message' => 'Paiement enregistre',
            'data' => $facture->fresh(),
        ]);
    }

    /**
     * Liste des statuts
     */
    public function listeStatuts(): JsonResponse
    {
        $statuts = collect(StatutFacture::cases())->map(fn($s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ]);

        return response()->json($statuts);
    }

    /**
     * Liste des statuts de paiement
     */
    public function listeStatutsPaiement(): JsonResponse
    {
        $statuts = collect(StatutPaiement::cases())->map(fn($s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ]);

        return response()->json($statuts);
    }

    /**
     * Liste des types de facture
     */
    public function listeTypes(): JsonResponse
    {
        $types = collect(\App\Enums\TypeFacture::cases())->map(fn($t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'color' => $t->color(),
        ]);

        return response()->json($types);
    }

    /**
     * Statistiques des factures
     */
    public function statistiques(Request $request): JsonResponse
    {
        $stats = [
            'total' => Facture::count(),
            'en_attente' => Facture::whereIn('statut', ['BROUILLON', 'A_RAPPROCHER', 'EN_RAPPROCHEMENT'])->count(),
            'a_valider' => Facture::where('statut', 'A_VALIDER')->count(),
            'validees' => Facture::where('statut', 'VALIDEE')->count(),
            'en_retard' => Facture::where('date_echeance', '<', now())
                ->whereNotIn('statut_paiement', ['PAYEE'])
                ->count(),
            'montant_total' => Facture::where('statut', 'VALIDEE')->sum('net_a_payer'),
            'montant_paye' => Facture::where('statut', 'VALIDEE')->sum('montant_paye'),
            'montant_restant' => Facture::where('statut', 'VALIDEE')
                ->selectRaw('SUM(net_a_payer - COALESCE(montant_paye, 0)) as total')
                ->value('total') ?? 0,
        ];

        return response()->json($stats);
    }
}
