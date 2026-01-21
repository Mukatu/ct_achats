<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EcheanceContrat;
use App\Models\Contrat;
use App\Enums\StatutEcheance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EcheanceContratController extends Controller
{
    /**
     * Liste des echeances (toutes ou filtrees)
     */
    public function index(Request $request): JsonResponse
    {
        $query = EcheanceContrat::with(['contrat.fournisseur', 'contrat.typeContrat', 'contrat.direction'])
            ->orderBy('date_echeance');

        // Filtres
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('contrat_id')) {
            $query->where('contrat_id', $request->contrat_id);
        }

        if ($request->has('annee')) {
            $query->whereYear('date_echeance', $request->annee);
        }

        if ($request->has('mois')) {
            $query->whereMonth('date_echeance', $request->mois);
        }

        if ($request->boolean('en_retard')) {
            $query->enRetard();
        }

        if ($request->boolean('a_traiter')) {
            $query->aTraiter();
        }

        if ($request->boolean('du_mois')) {
            $query->duMois();
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('numero_facture', 'like', "%{$search}%")
                    ->orWhereHas('contrat', fn($c) =>
                        $c->where('numero', 'like', "%{$search}%")
                            ->orWhere('objet', 'like', "%{$search}%")
                    );
            });
        }

        $perPage = $request->get('per_page', 15);
        $data = $query->paginate($perPage);

        return response()->json($data);
    }

    /**
     * Afficher une echeance
     */
    public function show(string $id): JsonResponse
    {
        $echeance = EcheanceContrat::with([
            'contrat.typeContrat',
            'contrat.fournisseur',
            'contrat.direction',
            'bonCommande',
            'createdBy',
            'validatedBy',
        ])->findOrFail($id);

        return response()->json($echeance);
    }

    /**
     * Mettre a jour une echeance
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $echeance = EcheanceContrat::findOrFail($id);

        if ($echeance->statut->isTerminal()) {
            return response()->json([
                'message' => 'Cette echeance ne peut plus etre modifiee'
            ], 422);
        }

        $validated = $request->validate([
            'date_echeance' => 'sometimes|date',
            'montant_prevu' => 'sometimes|numeric|min:0',
            'commentaire' => 'nullable|string',
        ]);

        $echeance->update($validated);

        return response()->json([
            'message' => 'Echeance mise a jour',
            'data' => $echeance->fresh(),
        ]);
    }

    /**
     * Enregistrer la facture recue
     */
    public function enregistrerFacture(Request $request, string $id): JsonResponse
    {
        $echeance = EcheanceContrat::findOrFail($id);

        if (!$echeance->statut->isPayable() && $echeance->statut !== StatutEcheance::A_VENIR) {
            return response()->json([
                'message' => 'Cette echeance ne peut pas recevoir de facture'
            ], 422);
        }

        $validated = $request->validate([
            'numero_facture' => 'required|string|max:100',
            'montant_facture' => 'required|numeric|min:0',
            'montant_ht' => 'nullable|numeric|min:0',
            'montant_tva' => 'nullable|numeric|min:0',
            'date_facture' => 'nullable|date',
            'commentaire' => 'nullable|string',
        ]);

        $echeance->marquerFacturee(
            $validated['numero_facture'],
            $validated['montant_facture'],
            $validated['montant_ht'] ?? null,
            $validated['montant_tva'] ?? null,
            $validated['date_facture'] ? new \DateTime($validated['date_facture']) : null
        );

        if (isset($validated['commentaire'])) {
            $echeance->update(['commentaire' => $validated['commentaire']]);
        }

        return response()->json([
            'message' => 'Facture enregistree',
            'data' => $echeance->fresh(),
        ]);
    }

    /**
     * Marquer comme payee
     */
    public function marquerPayee(Request $request, string $id): JsonResponse
    {
        $echeance = EcheanceContrat::findOrFail($id);

        if ($echeance->statut !== StatutEcheance::EN_COURS) {
            return response()->json([
                'message' => 'Cette echeance doit etre en cours de traitement pour etre marquee comme payee'
            ], 422);
        }

        $validated = $request->validate([
            'reference_paiement' => 'required|string|max:100',
            'date_paiement' => 'nullable|date',
        ]);

        $echeance->marquerPayee(
            $validated['reference_paiement'],
            $validated['date_paiement'] ? new \DateTime($validated['date_paiement']) : null
        );

        return response()->json([
            'message' => 'Echeance marquee comme payee',
            'data' => $echeance->fresh(),
        ]);
    }

    /**
     * Annuler une echeance
     */
    public function annuler(Request $request, string $id): JsonResponse
    {
        $echeance = EcheanceContrat::findOrFail($id);

        if ($echeance->statut->isTerminal()) {
            return response()->json([
                'message' => 'Cette echeance ne peut pas etre annulee'
            ], 422);
        }

        $validated = $request->validate([
            'motif' => 'nullable|string|max:500',
        ]);

        $echeance->annuler($validated['motif'] ?? null);

        return response()->json([
            'message' => 'Echeance annulee',
            'data' => $echeance->fresh(),
        ]);
    }

    /**
     * Marquer une echeance A_VENIR comme A_TRAITER
     */
    public function aTraiter(string $id): JsonResponse
    {
        $echeance = EcheanceContrat::findOrFail($id);

        if ($echeance->statut !== StatutEcheance::A_VENIR) {
            return response()->json([
                'message' => 'Seules les echeances a venir peuvent etre passees a traiter'
            ], 422);
        }

        $echeance->update(['statut' => StatutEcheance::A_TRAITER]);

        return response()->json([
            'message' => 'Echeance passee a traiter',
            'data' => $echeance->fresh(),
        ]);
    }

    /**
     * Liste des statuts pour dropdown
     */
    public function listeStatuts(): JsonResponse
    {
        $statuts = collect(StatutEcheance::cases())->map(fn($s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ]);

        return response()->json($statuts);
    }

    /**
     * Statistiques des echeances
     */
    public function statistiques(Request $request): JsonResponse
    {
        $annee = $request->get('annee', now()->year);

        $stats = [
            'a_venir' => EcheanceContrat::aVenir()->count(),
            'a_traiter' => EcheanceContrat::aTraiter()->count(),
            'en_retard' => EcheanceContrat::enRetard()->count(),
            'du_mois' => EcheanceContrat::duMois()->count(),
            'montant_mois' => EcheanceContrat::duMois()->sum('montant_prevu'),
            'montant_annee' => EcheanceContrat::whereYear('date_echeance', $annee)->sum('montant_prevu'),
            'paye_annee' => EcheanceContrat::whereYear('date_echeance', $annee)
                ->where('statut', StatutEcheance::PAYE)
                ->sum('montant_facture'),
        ];

        return response()->json($stats);
    }

    /**
     * Calendrier des echeances
     */
    public function calendrier(Request $request): JsonResponse
    {
        $annee = $request->get('annee', now()->year);
        $mois = $request->get('mois');

        $query = EcheanceContrat::with(['contrat.fournisseur'])
            ->whereYear('date_echeance', $annee)
            ->whereIn('statut', [
                StatutEcheance::A_VENIR,
                StatutEcheance::A_TRAITER,
                StatutEcheance::EN_COURS,
            ]);

        if ($mois) {
            $query->whereMonth('date_echeance', $mois);
        }

        $echeances = $query->orderBy('date_echeance')->get();

        // Grouper par date
        $calendrier = $echeances->groupBy(fn($e) => $e->date_echeance->format('Y-m-d'));

        return response()->json($calendrier);
    }
}
