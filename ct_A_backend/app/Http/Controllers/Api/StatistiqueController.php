<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpressionBesoin;
use App\Models\DemandeAchat;
use App\Models\BonCommande;
use App\Models\Reception;
use App\Models\Facture;
use App\Models\Fournisseur;
use App\Models\Direction;
use App\Enums\StatutEB;
use App\Enums\StatutDA;
use App\Enums\StatutBC;
use App\Enums\StatutBR;
use App\Enums\StatutFacture;
use App\Enums\StatutPaiement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatistiqueController extends Controller
{
    /**
     * Vue d'ensemble - compteurs globaux
     */
    public function overview(Request $request): JsonResponse
    {
        $annee = $request->get('annee', date('Y'));

        return response()->json([
            'annee' => $annee,
            'expressions_besoin' => $this->statsEB($annee),
            'demandes_achat' => $this->statsDA($annee),
            'demandes_achat_caisse' => $this->statsDAC($annee),
            'bons_commande' => $this->statsBC($annee),
            'receptions' => $this->statsBR($annee),
            'factures' => $this->statsFactures($annee),
            'fournisseurs' => $this->statsFournisseurs(),
            'engagements' => $this->statsEngagements($annee),
        ]);
    }

    /**
     * Stats EB
     */
    private function statsEB(int $annee): array
    {
        $total = ExpressionBesoin::whereYear('date_expression', $annee)->count();
        $parStatut = ExpressionBesoin::whereYear('date_expression', $annee)
            ->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        return [
            'total' => $total,
            'par_statut' => $parStatut,
            'en_cours' => ($parStatut[StatutEB::EN_SUSPENS->value] ?? 0) +
                         ($parStatut[StatutEB::EN_COURS_ACH->value] ?? 0) +
                         ($parStatut[StatutEB::EN_COURS_CDG->value] ?? 0) +
                         ($parStatut[StatutEB::EN_COURS_DFC->value] ?? 0) +
                         ($parStatut[StatutEB::EN_COURS_DG->value] ?? 0),
            'traitees' => $parStatut[StatutEB::TRAITE->value] ?? 0,
            'annulees' => $parStatut[StatutEB::ANNULE->value] ?? 0,
        ];
    }

    /**
     * Stats DA (Demandes d'Achat uniquement, sans DAC)
     */
    private function statsDA(int $annee): array
    {
        $query = DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DA');

        $total = $query->count();
        $parStatut = DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DA')
            ->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        $montantTotal = DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DA')
            ->whereNotIn('statut', [StatutDA::ANNULE->value])
            ->sum('montant');

        return [
            'total' => $total,
            'par_statut' => $parStatut,
            'en_cours' => ($parStatut[StatutDA::EN_SUSPENS->value] ?? 0) +
                         ($parStatut[StatutDA::EN_COURS_ACH->value] ?? 0) +
                         ($parStatut[StatutDA::EN_COURS_CDG->value] ?? 0) +
                         ($parStatut[StatutDA::EN_COURS_DFC->value] ?? 0) +
                         ($parStatut[StatutDA::EN_COURS_DG->value] ?? 0),
            'traitees' => $parStatut[StatutDA::TRAITE->value] ?? 0,
            'montant_total' => $montantTotal,
        ];
    }

    /**
     * Stats DAC (Demandes d'Achat Caisse)
     */
    private function statsDAC(int $annee): array
    {
        $total = DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DAC')
            ->count();

        $parStatut = DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DAC')
            ->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        $traitees = DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DAC')
            ->where('statut', StatutDA::TRAITE->value)
            ->count();

        $cloturees = DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DAC')
            ->whereNotNull('date_cloture')
            ->count();

        $montantTotal = DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DAC')
            ->whereNotIn('statut', [StatutDA::ANNULE->value])
            ->sum('montant');

        $montantPaye = DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DAC')
            ->whereNotNull('date_cloture')
            ->sum('montant_paye');

        return [
            'total' => $total,
            'par_statut' => $parStatut,
            'en_cours' => ($parStatut[StatutDA::EN_SUSPENS->value] ?? 0) +
                         ($parStatut[StatutDA::EN_COURS_ACH->value] ?? 0) +
                         ($parStatut[StatutDA::EN_COURS_CDG->value] ?? 0) +
                         ($parStatut[StatutDA::EN_COURS_DFC->value] ?? 0) +
                         ($parStatut[StatutDA::EN_COURS_DG->value] ?? 0),
            'traitees' => $traitees,
            'cloturees' => $cloturees,
            'en_attente_cloture' => $traitees - $cloturees,
            'montant_total' => $montantTotal,
            'montant_paye' => $montantPaye,
            'taux_cloture' => $traitees > 0 ? round(($cloturees / $traitees) * 100, 1) : 0,
        ];
    }

    /**
     * Stats Engagements (BC + DAC)
     */
    private function statsEngagements(int $annee): array
    {
        // Engagement BC (montant TTC des BC non annulés)
        $engagementBC = BonCommande::whereYear('date_bc', $annee)
            ->whereNotIn('statut', [StatutBC::ANNULE->value])
            ->sum('montant_ttc_xaf');

        // Engagement DAC (montant des DAC traitées ou clôturées, non annulées)
        $engagementDAC = DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DAC')
            ->whereNotIn('statut', [StatutDA::ANNULE->value])
            ->where(function ($q) {
                $q->where('statut', StatutDA::TRAITE->value)
                    ->orWhereNotNull('date_cloture');
            })
            ->sum('montant');

        return [
            'bc' => $engagementBC,
            'dac' => $engagementDAC,
            'total' => $engagementBC + $engagementDAC,
        ];
    }

    /**
     * Stats BC
     */
    private function statsBC(int $annee): array
    {
        $total = BonCommande::whereYear('date_bc', $annee)->count();
        $parStatut = BonCommande::whereYear('date_bc', $annee)
            ->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        $montantEngage = BonCommande::whereYear('date_bc', $annee)
            ->whereNotIn('statut', [StatutBC::ANNULE->value])
            ->sum('montant_ttc_xaf');

        $montantReceptionne = BonCommande::whereYear('date_bc', $annee)
            ->whereIn('statut', [StatutBC::LIVRAISON_PARTIELLE->value, StatutBC::LIVRE->value, StatutBC::TRAITE->value])
            ->sum('montant_ttc_xaf');

        return [
            'total' => $total,
            'par_statut' => $parStatut,
            'en_cours' => ($parStatut[StatutBC::NC->value] ?? 0) +
                         ($parStatut[StatutBC::EN_COURS_A->value] ?? 0) +
                         ($parStatut[StatutBC::EN_COURS_CDG->value] ?? 0) +
                         ($parStatut[StatutBC::EN_COURS_DFC->value] ?? 0) +
                         ($parStatut[StatutBC::DAC_CDG->value] ?? 0) +
                         ($parStatut[StatutBC::DAC_DG->value] ?? 0) +
                         ($parStatut[StatutBC::DAC_TRESO->value] ?? 0),
            'envoyes' => $parStatut[StatutBC::EN_COURS_FSSEUR->value] ?? 0,
            'livres' => ($parStatut[StatutBC::LIVRE->value] ?? 0) + ($parStatut[StatutBC::LIVRAISON_PARTIELLE->value] ?? 0),
            'montant_engage' => $montantEngage,
            'montant_receptionne' => $montantReceptionne,
        ];
    }

    /**
     * Stats BR
     */
    private function statsBR(int $annee): array
    {
        $total = Reception::whereYear('date_reception', $annee)->count();
        $parStatut = Reception::whereYear('date_reception', $annee)
            ->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        return [
            'total' => $total,
            'par_statut' => $parStatut,
            'en_attente' => $parStatut[StatutBR::BROUILLON->value] ?? 0,
            'validees' => $parStatut[StatutBR::VALIDEE->value] ?? 0,
        ];
    }

    /**
     * Stats Factures
     */
    private function statsFactures(int $annee): array
    {
        $total = Facture::whereYear('date_facture', $annee)->count();
        $parStatut = Facture::whereYear('date_facture', $annee)
            ->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();

        $parStatutPaiement = Facture::whereYear('date_facture', $annee)
            ->select('statut_paiement', DB::raw('count(*) as total'))
            ->groupBy('statut_paiement')
            ->pluck('total', 'statut_paiement')
            ->toArray();

        $montantTotal = Facture::whereYear('date_facture', $annee)
            ->where('statut', StatutFacture::VALIDEE->value)
            ->sum('net_a_payer');

        $montantPaye = Facture::whereYear('date_facture', $annee)
            ->where('statut', StatutFacture::VALIDEE->value)
            ->sum('montant_paye');

        $enRetard = Facture::whereYear('date_facture', $annee)
            ->where('date_echeance', '<', now())
            ->where('statut_paiement', '!=', StatutPaiement::PAYEE->value)
            ->count();

        return [
            'total' => $total,
            'par_statut' => $parStatut,
            'par_statut_paiement' => $parStatutPaiement,
            'validees' => $parStatut[StatutFacture::VALIDEE->value] ?? 0,
            'en_retard' => $enRetard,
            'montant_total' => $montantTotal,
            'montant_paye' => $montantPaye,
            'montant_restant' => $montantTotal - $montantPaye,
        ];
    }

    /**
     * Stats Fournisseurs
     */
    private function statsFournisseurs(): array
    {
        $total = Fournisseur::count();
        $actifs = Fournisseur::where('statut', 'ACTIF')->count();
        $parType = Fournisseur::select('type_fournisseur', DB::raw('count(*) as total'))
            ->groupBy('type_fournisseur')
            ->pluck('total', 'type_fournisseur')
            ->toArray();

        return [
            'total' => $total,
            'actifs' => $actifs,
            'inactifs' => $total - $actifs,
            'par_type' => $parType,
        ];
    }

    /**
     * Évolution mensuelle
     */
    public function evolutionMensuelle(Request $request): JsonResponse
    {
        $annee = $request->get('annee', date('Y'));

        // EB par mois
        $ebParMois = ExpressionBesoin::selectRaw('MONTH(date_expression) as mois, count(*) as total')
            ->whereYear('date_expression', $annee)
            ->groupBy(DB::raw('MONTH(date_expression)'))
            ->orderBy('mois')
            ->pluck('total', 'mois');

        // DA par mois
        $daParMois = DemandeAchat::selectRaw('MONTH(date_demande) as mois, count(*) as total')
            ->whereYear('date_demande', $annee)
            ->groupBy(DB::raw('MONTH(date_demande)'))
            ->orderBy('mois')
            ->pluck('total', 'mois');

        // BC par mois (nombre et montant)
        $bcParMois = BonCommande::selectRaw('MONTH(date_bc) as mois, count(*) as total, SUM(montant_ttc_xaf) as montant')
            ->whereYear('date_bc', $annee)
            ->whereNotIn('statut', [StatutBC::ANNULE->value])
            ->groupBy(DB::raw('MONTH(date_bc)'))
            ->orderBy('mois')
            ->get()
            ->keyBy('mois');

        // Factures par mois
        $facturesParMois = Facture::selectRaw('MONTH(date_facture) as mois, count(*) as total, SUM(net_a_payer) as montant')
            ->whereYear('date_facture', $annee)
            ->where('statut', StatutFacture::VALIDEE->value)
            ->groupBy(DB::raw('MONTH(date_facture)'))
            ->orderBy('mois')
            ->get()
            ->keyBy('mois');

        // Construire les données pour tous les mois
        $moisLabels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        $data = [];

        for ($i = 1; $i <= 12; $i++) {
            $data[] = [
                'mois' => $i,
                'label' => $moisLabels[$i - 1],
                'eb' => $ebParMois[$i] ?? 0,
                'da' => $daParMois[$i] ?? 0,
                'bc_count' => $bcParMois[$i]->total ?? 0,
                'bc_montant' => $bcParMois[$i]->montant ?? 0,
                'factures_count' => $facturesParMois[$i]->total ?? 0,
                'factures_montant' => $facturesParMois[$i]->montant ?? 0,
            ];
        }

        return response()->json([
            'annee' => $annee,
            'evolution' => $data,
        ]);
    }

    /**
     * Top fournisseurs
     */
    public function topFournisseurs(Request $request): JsonResponse
    {
        $annee = $request->get('annee', date('Y'));
        $limit = $request->get('limit', 10);

        $topParMontant = BonCommande::join('fournisseurs', 'bons_commande.fournisseur_id', '=', 'fournisseurs.id')
            ->selectRaw('fournisseurs.id, fournisseurs.raison_sociale, COUNT(*) as nb_commandes, SUM(montant_ttc_xaf) as total')
            ->whereYear('date_bc', $annee)
            ->whereNotIn('bons_commande.statut', [StatutBC::ANNULE->value])
            ->groupBy('fournisseurs.id', 'fournisseurs.raison_sociale')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $topParNombre = BonCommande::join('fournisseurs', 'bons_commande.fournisseur_id', '=', 'fournisseurs.id')
            ->selectRaw('fournisseurs.id, fournisseurs.raison_sociale, COUNT(*) as nb_commandes, SUM(montant_ttc_xaf) as total')
            ->whereYear('date_bc', $annee)
            ->whereNotIn('bons_commande.statut', [StatutBC::ANNULE->value])
            ->groupBy('fournisseurs.id', 'fournisseurs.raison_sociale')
            ->orderByDesc('nb_commandes')
            ->limit($limit)
            ->get();

        return response()->json([
            'annee' => $annee,
            'par_montant' => $topParMontant,
            'par_nombre' => $topParNombre,
        ]);
    }

    /**
     * Répartition par direction
     */
    public function parDirection(Request $request): JsonResponse
    {
        $annee = $request->get('annee', date('Y'));

        $bcParDirection = BonCommande::join('directions', 'bons_commande.direction_id', '=', 'directions.id')
            ->selectRaw('directions.id, directions.libelle_court as direction, COUNT(*) as nb_commandes, SUM(montant_ttc_xaf) as total')
            ->whereYear('date_bc', $annee)
            ->whereNotIn('bons_commande.statut', [StatutBC::ANNULE->value])
            ->groupBy('directions.id', 'directions.libelle_court')
            ->orderByDesc('total')
            ->get();

        $ebParDirection = ExpressionBesoin::join('directions', 'expressions_besoin.direction_id', '=', 'directions.id')
            ->selectRaw('directions.id, directions.libelle_court as direction, COUNT(*) as total')
            ->whereYear('date_expression', $annee)
            ->groupBy('directions.id', 'directions.libelle_court')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'annee' => $annee,
            'bons_commande' => $bcParDirection,
            'expressions_besoin' => $ebParDirection,
        ]);
    }

    /**
     * Délais moyens de traitement
     */
    public function delais(Request $request): JsonResponse
    {
        $annee = $request->get('annee', date('Y'));

        // Délai moyen EB -> DA (en jours)
        $delaiEbDa = DemandeAchat::join('expressions_besoin', 'demandes_achat.expression_besoin_id', '=', 'expressions_besoin.id')
            ->whereYear('demandes_achat.date_demande', $annee)
            ->selectRaw('AVG(DATEDIFF(demandes_achat.date_demande, expressions_besoin.date_expression)) as delai_moyen')
            ->value('delai_moyen');

        // Délai moyen DA -> BC
        $delaiDaBc = BonCommande::join('demandes_achat', 'bons_commande.demande_achat_id', '=', 'demandes_achat.id')
            ->whereYear('bons_commande.date_bc', $annee)
            ->selectRaw('AVG(DATEDIFF(bons_commande.date_bc, demandes_achat.date_demande)) as delai_moyen')
            ->value('delai_moyen');

        // Délai moyen BC -> Réception
        $delaiBcBr = Reception::join('bons_commande', 'receptions.bon_commande_id', '=', 'bons_commande.id')
            ->whereYear('receptions.date_reception', $annee)
            ->selectRaw('AVG(DATEDIFF(receptions.date_reception, bons_commande.date_bc)) as delai_moyen')
            ->value('delai_moyen');

        // Délai moyen Facture -> Paiement
        $delaiPaiement = Facture::whereYear('date_facture', $annee)
            ->whereNotNull('date_paiement')
            ->where('statut_paiement', StatutPaiement::PAYEE->value)
            ->selectRaw('AVG(DATEDIFF(date_paiement, date_facture)) as delai_moyen')
            ->value('delai_moyen');

        return response()->json([
            'annee' => $annee,
            'delais' => [
                'eb_vers_da' => round($delaiEbDa ?? 0, 1),
                'da_vers_bc' => round($delaiDaBc ?? 0, 1),
                'bc_vers_reception' => round($delaiBcBr ?? 0, 1),
                'facture_vers_paiement' => round($delaiPaiement ?? 0, 1),
            ],
        ]);
    }

    /**
     * Alertes et indicateurs
     */
    public function alertes(): JsonResponse
    {
        // EB en attente depuis longtemps (> 7 jours)
        $ebEnRetard = ExpressionBesoin::whereIn('statut', [StatutEB::EN_SUSPENS->value, StatutEB::EN_COURS_ACH->value])
            ->where('created_at', '<', now()->subDays(7))
            ->count();

        // DA en attente de validation
        $daEnAttente = DemandeAchat::whereIn('statut', [StatutDA::EN_COURS_CDG->value, StatutDA::EN_COURS_DG->value])
            ->count();

        // BC non réceptionnés (envoyés depuis > 30 jours)
        $bcNonReceptionnes = BonCommande::where('statut', StatutBC::EN_COURS_FSSEUR->value)
            ->where('date_bc', '<', now()->subDays(30))
            ->count();

        // Factures en retard de paiement
        $facturesEnRetard = Facture::where('date_echeance', '<', now())
            ->whereNotIn('statut_paiement', [StatutPaiement::PAYEE->value])
            ->where('statut', StatutFacture::VALIDEE->value)
            ->count();

        // Montant factures en retard
        $montantEnRetard = Facture::where('date_echeance', '<', now())
            ->whereNotIn('statut_paiement', [StatutPaiement::PAYEE->value])
            ->where('statut', StatutFacture::VALIDEE->value)
            ->selectRaw('SUM(net_a_payer - COALESCE(montant_paye, 0)) as total')
            ->value('total') ?? 0;

        return response()->json([
            'alertes' => [
                'eb_en_retard' => $ebEnRetard,
                'da_en_attente' => $daEnAttente,
                'bc_non_receptionnes' => $bcNonReceptionnes,
                'factures_en_retard' => $facturesEnRetard,
                'montant_en_retard' => $montantEnRetard,
            ],
        ]);
    }

    /**
     * Années disponibles
     */
    public function anneesDisponibles(): JsonResponse
    {
        // Chercher les années avec données dans toutes les tables principales
        $annees = collect();

        $annees = $annees->merge(
            ExpressionBesoin::selectRaw('YEAR(date_expression) as annee')->distinct()->pluck('annee')
        );
        $annees = $annees->merge(
            DemandeAchat::selectRaw('YEAR(date_demande) as annee')->distinct()->pluck('annee')
        );
        $annees = $annees->merge(
            BonCommande::selectRaw('YEAR(date_bc) as annee')->distinct()->pluck('annee')
        );

        $annees = $annees->unique()->sort()->reverse()->values()->toArray();

        // Si pas de données, retourner l'année courante
        if (empty($annees)) {
            $annees = [(int) date('Y')];
        }

        return response()->json([
            'annees' => $annees,
            'annee_courante' => (int) date('Y'),
        ]);
    }
}
