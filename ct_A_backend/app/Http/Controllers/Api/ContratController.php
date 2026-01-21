<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\EcheanceContrat;
use App\Services\NumerotationService;
use App\Enums\StatutContrat;
use App\Enums\StatutEcheance;
use App\Enums\PeriodiciteContrat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ContratController extends Controller
{
    protected NumerotationService $numerotation;

    public function __construct(NumerotationService $numerotation)
    {
        $this->numerotation = $numerotation;
    }

    /**
     * Liste des contrats
     */
    public function index(Request $request): JsonResponse
    {
        $query = Contrat::with([
            'typeContrat',
            'fournisseur',
            'zone',
            'direction',
            'responsable'
        ])->orderBy('created_at', 'desc');

        // Filtres
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('type_contrat_id')) {
            $query->where('type_contrat_id', $request->type_contrat_id);
        }

        if ($request->has('fournisseur_id')) {
            $query->where('fournisseur_id', $request->fournisseur_id);
        }

        if ($request->has('direction_id')) {
            $query->where('direction_id', $request->direction_id);
        }

        if ($request->boolean('a_renouveler')) {
            $jours = $request->get('jours_avant_expiration', 90);
            $query->aRenouveler($jours);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('objet', 'like', "%{$search}%")
                    ->orWhere('reference_externe', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $data = $query->paginate($perPage);

        return response()->json($data);
    }

    /**
     * Creer un nouveau contrat
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type_contrat_id' => 'required|uuid|exists:types_contrat,id',
            'fournisseur_id' => 'required|uuid|exists:fournisseurs,id',
            'reference_externe' => 'nullable|string|max:100',
            'zone_id' => 'nullable|uuid|exists:zones,id',
            'direction_id' => 'required|uuid|exists:directions,id',
            'service_id' => 'nullable|uuid|exists:services,id',
            'objet' => 'required|string|max:500',
            'description' => 'nullable|string',
            'date_signature' => 'required|date',
            'date_debut' => 'required|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'reconduction_tacite' => 'boolean',
            'preavis_jours' => 'nullable|integer|min:0',
            'periodicite' => 'required|in:MENSUEL,TRIMESTRIEL,SEMESTRIEL,ANNUEL,PONCTUEL',
            'montant_periodique' => 'required|numeric|min:0',
            'taux_tva' => 'nullable|numeric|min:0|max:100',
            'tva_incluse' => 'boolean',
            'conditions_paiement' => 'nullable|string|max:255',
            'jour_facturation' => 'nullable|integer|min:1|max:31',
            'responsable_id' => 'nullable|uuid|exists:users,id',
            'contact_fournisseur' => 'nullable|string|max:255',
            'commentaire' => 'nullable|string',
            'generer_echeances' => 'boolean',
        ]);

        $user = auth()->user();
        $societeId = $user->service?->direction?->zone?->societe_id ?? $user->societe_id;

        $contrat = DB::transaction(function () use ($validated, $user, $societeId, $request) {
            $numero = $this->numerotation->genererNumero($societeId, 'CTR');

            $contrat = Contrat::create([
                'societe_id' => $societeId,
                'numero' => $numero,
                'type_contrat_id' => $validated['type_contrat_id'],
                'fournisseur_id' => $validated['fournisseur_id'],
                'reference_externe' => $validated['reference_externe'] ?? null,
                'zone_id' => $validated['zone_id'] ?? null,
                'direction_id' => $validated['direction_id'],
                'service_id' => $validated['service_id'] ?? null,
                'objet' => $validated['objet'],
                'description' => $validated['description'] ?? null,
                'date_signature' => $validated['date_signature'],
                'date_debut' => $validated['date_debut'],
                'date_fin' => $validated['date_fin'] ?? null,
                'reconduction_tacite' => $validated['reconduction_tacite'] ?? false,
                'preavis_jours' => $validated['preavis_jours'] ?? null,
                'periodicite' => $validated['periodicite'],
                'montant_periodique' => $validated['montant_periodique'],
                'taux_tva' => $validated['taux_tva'] ?? 19.25,
                'tva_incluse' => $validated['tva_incluse'] ?? false,
                'conditions_paiement' => $validated['conditions_paiement'] ?? null,
                'jour_facturation' => $validated['jour_facturation'] ?? 1,
                'responsable_id' => $validated['responsable_id'] ?? null,
                'contact_fournisseur' => $validated['contact_fournisseur'] ?? null,
                'commentaire' => $validated['commentaire'] ?? null,
                'statut' => StatutContrat::BROUILLON,
                'created_by' => $user->id,
            ]);

            // Generer les echeances si demande
            if ($request->boolean('generer_echeances')) {
                $contrat->genererEcheances();
            }

            return $contrat;
        });

        return response()->json([
            'message' => 'Contrat cree avec succes',
            'data' => $contrat->load([
                'typeContrat',
                'fournisseur',
                'zone',
                'direction',
                'service',
                'responsable',
                'echeances'
            ]),
        ], 201);
    }

    /**
     * Afficher un contrat
     */
    public function show(string $id): JsonResponse
    {
        $contrat = Contrat::with([
            'typeContrat',
            'fournisseur',
            'zone',
            'direction',
            'service',
            'responsable',
            'createdBy',
            'echeances' => fn($q) => $q->orderBy('date_echeance'),
        ])->findOrFail($id);

        return response()->json($contrat);
    }

    /**
     * Mettre a jour un contrat
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $contrat = Contrat::findOrFail($id);

        if (!$contrat->statut->canBeEdited()) {
            return response()->json([
                'message' => 'Ce contrat ne peut plus etre modifie'
            ], 422);
        }

        $validated = $request->validate([
            'type_contrat_id' => 'sometimes|uuid|exists:types_contrat,id',
            'fournisseur_id' => 'sometimes|uuid|exists:fournisseurs,id',
            'reference_externe' => 'nullable|string|max:100',
            'zone_id' => 'nullable|uuid|exists:zones,id',
            'direction_id' => 'sometimes|uuid|exists:directions,id',
            'service_id' => 'nullable|uuid|exists:services,id',
            'objet' => 'sometimes|string|max:500',
            'description' => 'nullable|string',
            'date_signature' => 'sometimes|date',
            'date_debut' => 'sometimes|date',
            'date_fin' => 'nullable|date',
            'reconduction_tacite' => 'boolean',
            'preavis_jours' => 'nullable|integer|min:0',
            'periodicite' => 'sometimes|in:MENSUEL,TRIMESTRIEL,SEMESTRIEL,ANNUEL,PONCTUEL',
            'montant_periodique' => 'sometimes|numeric|min:0',
            'taux_tva' => 'nullable|numeric|min:0|max:100',
            'tva_incluse' => 'boolean',
            'conditions_paiement' => 'nullable|string|max:255',
            'jour_facturation' => 'nullable|integer|min:1|max:31',
            'responsable_id' => 'nullable|uuid|exists:users,id',
            'contact_fournisseur' => 'nullable|string|max:255',
            'commentaire' => 'nullable|string',
        ]);

        $contrat->update($validated);

        return response()->json([
            'message' => 'Contrat mis a jour',
            'data' => $contrat->fresh([
                'typeContrat',
                'fournisseur',
                'zone',
                'direction',
                'service',
                'responsable'
            ]),
        ]);
    }

    /**
     * Supprimer un contrat
     */
    public function destroy(string $id): JsonResponse
    {
        $contrat = Contrat::findOrFail($id);

        if ($contrat->statut !== StatutContrat::BROUILLON) {
            return response()->json([
                'message' => 'Seuls les contrats en brouillon peuvent etre supprimes'
            ], 422);
        }

        $contrat->echeances()->delete();
        $contrat->forceDelete();

        return response()->json([
            'message' => 'Contrat supprime'
        ]);
    }

    /**
     * Activer un contrat
     */
    public function activer(string $id): JsonResponse
    {
        $contrat = Contrat::findOrFail($id);

        if ($contrat->statut !== StatutContrat::BROUILLON) {
            return response()->json([
                'message' => 'Seuls les contrats en brouillon peuvent etre actives'
            ], 422);
        }

        DB::transaction(function () use ($contrat) {
            $contrat->update(['statut' => StatutContrat::ACTIF]);

            // Generer les echeances si pas encore fait
            if ($contrat->echeances()->count() === 0) {
                $contrat->genererEcheances();
            }

            // Marquer les echeances passees comme A_TRAITER
            $contrat->echeances()
                ->where('statut', StatutEcheance::A_VENIR)
                ->where('date_echeance', '<=', now())
                ->update(['statut' => StatutEcheance::A_TRAITER]);
        });

        return response()->json([
            'message' => 'Contrat active',
            'data' => $contrat->fresh(['echeances']),
        ]);
    }

    /**
     * Suspendre un contrat
     */
    public function suspendre(Request $request, string $id): JsonResponse
    {
        $contrat = Contrat::findOrFail($id);

        if ($contrat->statut !== StatutContrat::ACTIF) {
            return response()->json([
                'message' => 'Seuls les contrats actifs peuvent etre suspendus'
            ], 422);
        }

        $validated = $request->validate([
            'motif' => 'nullable|string|max:500',
        ]);

        $contrat->update([
            'statut' => StatutContrat::SUSPENDU,
            'commentaire' => $validated['motif'] ?? $contrat->commentaire,
        ]);

        return response()->json([
            'message' => 'Contrat suspendu',
            'data' => $contrat->fresh(),
        ]);
    }

    /**
     * Reactiver un contrat suspendu
     */
    public function reactiver(string $id): JsonResponse
    {
        $contrat = Contrat::findOrFail($id);

        if ($contrat->statut !== StatutContrat::SUSPENDU) {
            return response()->json([
                'message' => 'Seuls les contrats suspendus peuvent etre reactives'
            ], 422);
        }

        $contrat->update(['statut' => StatutContrat::ACTIF]);

        return response()->json([
            'message' => 'Contrat reactive',
            'data' => $contrat->fresh(),
        ]);
    }

    /**
     * Terminer un contrat
     */
    public function terminer(Request $request, string $id): JsonResponse
    {
        $contrat = Contrat::findOrFail($id);

        if (!in_array($contrat->statut, [StatutContrat::ACTIF, StatutContrat::SUSPENDU])) {
            return response()->json([
                'message' => 'Ce contrat ne peut pas etre termine'
            ], 422);
        }

        $validated = $request->validate([
            'date_fin_effective' => 'nullable|date',
            'motif' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($contrat, $validated) {
            $contrat->update([
                'statut' => StatutContrat::TERMINE,
                'date_fin' => $validated['date_fin_effective'] ?? now(),
                'commentaire' => $validated['motif'] ?? $contrat->commentaire,
            ]);

            // Annuler les echeances A_VENIR
            $contrat->echeances()
                ->where('statut', StatutEcheance::A_VENIR)
                ->update(['statut' => StatutEcheance::ANNULE]);
        });

        return response()->json([
            'message' => 'Contrat termine',
            'data' => $contrat->fresh(),
        ]);
    }

    /**
     * Resilier un contrat
     */
    public function resilier(Request $request, string $id): JsonResponse
    {
        $contrat = Contrat::findOrFail($id);

        if (!in_array($contrat->statut, [StatutContrat::ACTIF, StatutContrat::SUSPENDU])) {
            return response()->json([
                'message' => 'Ce contrat ne peut pas etre resilie'
            ], 422);
        }

        $validated = $request->validate([
            'motif' => 'required|string|max:500',
            'date_resiliation' => 'nullable|date',
        ]);

        DB::transaction(function () use ($contrat, $validated) {
            $contrat->update([
                'statut' => StatutContrat::RESILIE,
                'date_fin' => $validated['date_resiliation'] ?? now(),
                'commentaire' => $validated['motif'],
            ]);

            // Annuler les echeances non payees
            $contrat->echeances()
                ->whereIn('statut', [StatutEcheance::A_VENIR, StatutEcheance::A_TRAITER])
                ->update(['statut' => StatutEcheance::ANNULE]);
        });

        return response()->json([
            'message' => 'Contrat resilie',
            'data' => $contrat->fresh(),
        ]);
    }

    /**
     * Generer les echeances pour un contrat
     */
    public function genererEcheances(Request $request, string $id): JsonResponse
    {
        $contrat = Contrat::findOrFail($id);

        $validated = $request->validate([
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
        ]);

        $dateDebut = $validated['date_debut'] ?? null;
        $dateFin = $validated['date_fin'] ?? null;

        $count = $contrat->genererEcheances(
            $dateDebut ? \Carbon\Carbon::parse($dateDebut) : null,
            $dateFin ? \Carbon\Carbon::parse($dateFin) : null
        );

        return response()->json([
            'message' => "{$count} echeance(s) generee(s)",
            'data' => $contrat->fresh(['echeances']),
        ]);
    }

    /**
     * Liste des echeances d'un contrat
     */
    public function echeances(Request $request, string $id): JsonResponse
    {
        $contrat = Contrat::findOrFail($id);

        $query = $contrat->echeances();

        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('annee')) {
            $query->whereYear('date_echeance', $request->annee);
        }

        $echeances = $query->orderBy('date_echeance')->get();

        return response()->json($echeances);
    }

    /**
     * Liste des statuts pour dropdown
     */
    public function listeStatuts(): JsonResponse
    {
        $statuts = collect(StatutContrat::cases())->map(fn($s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ]);

        return response()->json($statuts);
    }

    /**
     * Liste des periodicites pour dropdown
     */
    public function listePeriodicites(): JsonResponse
    {
        $periodicites = collect(PeriodiciteContrat::cases())->map(fn($p) => [
            'value' => $p->value,
            'label' => $p->label(),
            'nombre_mois' => $p->nombreMois(),
        ]);

        return response()->json($periodicites);
    }

    /**
     * Statistiques des contrats
     */
    public function statistiques(): JsonResponse
    {
        $stats = [
            'total' => Contrat::count(),
            'actifs' => Contrat::actif()->count(),
            'a_renouveler_30j' => Contrat::aRenouveler(30)->count(),
            'a_renouveler_90j' => Contrat::aRenouveler(90)->count(),
            'montant_annuel_total' => Contrat::actif()->sum('montant_annuel'),
            'echeances_a_traiter' => EcheanceContrat::aTraiter()->count(),
            'echeances_en_retard' => EcheanceContrat::enRetard()->count(),
        ];

        return response()->json($stats);
    }
}
