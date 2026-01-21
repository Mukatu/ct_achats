<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpressionBesoin;
use App\Models\DemandeAchat;
use App\Models\BonCommande;
use App\Models\Fournisseur;
use App\Models\Contrat;
use App\Models\EcheanceContrat;
use App\Enums\StatutEB;
use App\Enums\StatutDA;
use App\Enums\StatutBC;
use App\Enums\StatutContrat;
use App\Enums\StatutEcheance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Données du tableau de bord
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Compteurs par statut
        $ebParStatut = ExpressionBesoin::select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $daParStatut = DemandeAchat::select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $bcParStatut = BonCommande::select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut');

        // Mes tâches en attente (selon le rôle)
        $mesTaches = [];

        if ($user->hasRole('ACHETEUR')) {
            $mesTaches['eb_a_traiter'] = ExpressionBesoin::where('acheteur_id', $user->id)
                ->whereIn('statut', [StatutEB::EN_SUSPENS, StatutEB::EN_COURS_ACH])
                ->count();

            $mesTaches['da_a_traiter'] = DemandeAchat::where('acheteur_id', $user->id)
                ->whereIn('statut', [StatutDA::EN_COURS_ACH])
                ->count();
        }

        if ($user->hasRole('VALIDEUR_CDG')) {
            $mesTaches['eb_a_valider'] = ExpressionBesoin::where('statut', StatutEB::EN_COURS_ACH)
                ->where('estimation', '>=', config('ct_achats.seuils.cdg'))
                ->count();

            $mesTaches['da_a_valider'] = DemandeAchat::where('statut', StatutDA::EN_COURS_CDG)
                ->count();

            $mesTaches['bc_a_valider'] = BonCommande::where('statut', StatutBC::EN_COURS_CDG)
                ->count();
        }

        // Dernières activités
        $dernieresEB = ExpressionBesoin::with(['demandeur', 'direction'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $dernieresDA = DemandeAchat::with(['demandeur', 'direction'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $derniersBC = BonCommande::with(['fournisseur', 'direction'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'compteurs' => [
                'eb' => $ebParStatut,
                'da' => $daParStatut,
                'bc' => $bcParStatut,
            ],
            'mes_taches' => $mesTaches,
            'dernieres_activites' => [
                'eb' => $dernieresEB,
                'da' => $dernieresDA,
                'bc' => $derniersBC,
            ],
        ]);
    }

    /**
     * Statistiques détaillées
     */
    public function stats(Request $request): JsonResponse
    {
        $annee = $request->get('annee', date('Y'));

        // Montants par mois
        $montantsParMois = BonCommande::selectRaw('MONTH(date_bc) as mois, SUM(montant_ttc_xaf) as total')
            ->whereYear('date_bc', $annee)
            ->whereNotIn('statut', [StatutBC::ANNULE])
            ->groupBy(DB::raw('MONTH(date_bc)'))
            ->orderBy('mois')
            ->get()
            ->pluck('total', 'mois');

        // Montants par direction
        $montantsParDirection = BonCommande::join('directions', 'bons_commande.direction_id', '=', 'directions.id')
            ->selectRaw('directions.libelle_court as direction, SUM(montant_ttc_xaf) as total')
            ->whereYear('date_bc', $annee)
            ->whereNotIn('bons_commande.statut', [StatutBC::ANNULE])
            ->groupBy('directions.id', 'directions.libelle_court')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Top fournisseurs
        $topFournisseurs = BonCommande::join('fournisseurs', 'bons_commande.fournisseur_id', '=', 'fournisseurs.id')
            ->selectRaw('fournisseurs.raison_sociale, COUNT(*) as nb_commandes, SUM(montant_ttc_xaf) as total')
            ->whereYear('date_bc', $annee)
            ->whereNotIn('bons_commande.statut', [StatutBC::ANNULE])
            ->groupBy('fournisseurs.id', 'fournisseurs.raison_sociale')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Engagements BC (bons de commande aboutis)
        $engagementBC = BonCommande::whereYear('date_bc', $annee)
            ->whereNotIn('statut', [StatutBC::ANNULE])
            ->sum('montant_ttc_xaf');

        // Engagements DAC (demandes caisse clôturées - paiement direct)
        $engagementDAC = DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DAC')
            ->where('statut', StatutDA::TRAITE)
            ->sum(DB::raw('COALESCE(montant_paye, montant)'));

        // DAC clôturées (avec justificatif)
        $dacCloturees = DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DAC')
            ->whereNotNull('date_cloture')
            ->sum('montant_paye');

        // Totaux
        $totaux = [
            'eb_total' => ExpressionBesoin::whereYear('date_expression', $annee)->count(),
            'da_total' => DemandeAchat::whereYear('date_demande', $annee)->where('type_demande', 'DA')->count(),
            'dac_total' => DemandeAchat::whereYear('date_demande', $annee)->where('type_demande', 'DAC')->count(),
            'bc_total' => BonCommande::whereYear('date_bc', $annee)->count(),
            'montant_engage_bc' => $engagementBC,
            'montant_engage_dac' => $engagementDAC,
            'montant_engage' => $engagementBC + $engagementDAC, // Total engagements
            'dac_cloturees' => $dacCloturees,
            'fournisseurs_actifs' => Fournisseur::where('statut', 'ACTIF')->count(),
            'contrats_actifs' => Contrat::where('statut', StatutContrat::ACTIF)->count(),
        ];

        // Statistiques d'aboutissement
        $statsEB = $this->getStatistiquesEB($annee);
        $statsDA = $this->getStatistiquesDA($annee);
        $statsDAC = $this->getStatistiquesDAC($annee);
        $statsBC = $this->getStatistiquesBC($annee);

        // Statistiques contrats (isolées pour ne pas bloquer le reste en cas d'erreur)
        $statsContrat = null;
        try {
            $statsContrat = $this->getStatistiquesContrat($annee);
        } catch (\Exception $e) {
            Log::error('Erreur stats contrat: ' . $e->getMessage());
        }

        return response()->json([
            'annee' => $annee,
            'montants_par_mois' => $montantsParMois,
            'montants_par_direction' => $montantsParDirection,
            'top_fournisseurs' => $topFournisseurs,
            'totaux' => $totaux,
            'stats_eb' => $statsEB,
            'stats_da' => $statsDA,
            'stats_dac' => $statsDAC,
            'stats_bc' => $statsBC,
            'stats_contrat' => $statsContrat,
        ]);
    }

    /**
     * Statistiques d'aboutissement des EB
     */
    protected function getStatistiquesEB(int $annee): array
    {
        // Statuts considérés comme "aboutis" (transformé en DA ou traité)
        $statutsAboutis = [StatutEB::TRAITE];

        // Statuts en cours de traitement
        $statutsEnCours = [
            StatutEB::EN_SUSPENS,
            StatutEB::EN_COURS_ACH,
            StatutEB::EN_COURS_CDG,
            StatutEB::EN_COURS_DFC,
            StatutEB::EN_COURS_DG,
        ];

        // Compteurs par catégorie
        $ebTotal = ExpressionBesoin::whereYear('date_expression', $annee)->count();

        $ebAboutis = ExpressionBesoin::whereYear('date_expression', $annee)
            ->whereIn('statut', $statutsAboutis)
            ->count();

        $ebEnCours = ExpressionBesoin::whereYear('date_expression', $annee)
            ->whereIn('statut', $statutsEnCours)
            ->count();

        $ebAnnules = ExpressionBesoin::whereYear('date_expression', $annee)
            ->where('statut', StatutEB::ANNULE)
            ->count();

        $ebEnSuspens = ExpressionBesoin::whereYear('date_expression', $annee)
            ->where('statut', StatutEB::EN_SUSPENS)
            ->count();

        // Montants par catégorie (estimation)
        $montantAboutis = ExpressionBesoin::whereYear('date_expression', $annee)
            ->whereIn('statut', $statutsAboutis)
            ->sum('estimation');

        $montantEnCours = ExpressionBesoin::whereYear('date_expression', $annee)
            ->whereIn('statut', $statutsEnCours)
            ->sum('estimation');

        $montantAnnules = ExpressionBesoin::whereYear('date_expression', $annee)
            ->where('statut', StatutEB::ANNULE)
            ->sum('estimation');

        // Taux d'aboutissement (sur les EB terminés uniquement, hors en cours et en suspens)
        $ebTermines = $ebAboutis + $ebAnnules;
        $tauxAboutissement = $ebTermines > 0
            ? round(($ebAboutis / $ebTermines) * 100, 1)
            : 0;

        // Délai moyen de traitement (entre date_expression et updated_at pour les traités)
        $delaiMoyen = ExpressionBesoin::whereYear('date_expression', $annee)
            ->whereIn('statut', $statutsAboutis)
            ->get()
            ->map(function ($eb) {
                return $eb->date_expression->diffInDays($eb->updated_at);
            })
            ->avg();

        // Répartition par statut détaillé
        $repartitionStatuts = ExpressionBesoin::whereYear('date_expression', $annee)
            ->select('statut', DB::raw('COUNT(*) as nombre'), DB::raw('SUM(estimation) as montant'))
            ->groupBy('statut')
            ->get()
            ->map(function ($item) {
                return [
                    'statut' => $item->statut,
                    'label' => $item->statut instanceof StatutEB ? $item->statut->label() : $item->statut,
                    'nombre' => $item->nombre,
                    'montant' => $item->montant ?? 0,
                ];
            });

        return [
            'total' => $ebTotal,
            'aboutis' => $ebAboutis,
            'en_cours' => $ebEnCours,
            'annules' => $ebAnnules,
            'en_suspens' => $ebEnSuspens,
            'taux_aboutissement' => $tauxAboutissement,
            'delai_moyen_jours' => $delaiMoyen ? round($delaiMoyen, 1) : null,
            'montants' => [
                'aboutis' => $montantAboutis,
                'en_cours' => $montantEnCours,
                'annules' => $montantAnnules,
            ],
            'repartition_statuts' => $repartitionStatuts,
        ];
    }

    /**
     * Statistiques d'aboutissement des DA (Demandes d'Achat classiques, hors DAC)
     */
    protected function getStatistiquesDA(int $annee): array
    {
        // Filtre de base : uniquement les DA (pas les DAC)
        $baseQuery = fn() => DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DA');

        // Statuts considérés comme "aboutis" (transformé en BC)
        $statutsAboutis = [StatutDA::TRAITE];

        // Statuts en cours de traitement
        $statutsEnCours = [
            StatutDA::EN_SUSPENS,
            StatutDA::EN_COURS_ACH,
            StatutDA::EN_COURS_CDG,
            StatutDA::EN_COURS_DFC,
            StatutDA::EN_COURS_DG,
        ];

        // Compteurs par catégorie
        $daTotal = $baseQuery()->count();
        $daAboutis = $baseQuery()->whereIn('statut', $statutsAboutis)->count();
        $daEnSuspens = $baseQuery()->where('statut', StatutDA::EN_SUSPENS)->count();
        $daCDG = $baseQuery()->where('statut', StatutDA::EN_COURS_CDG)->count();
        $daDFC = $baseQuery()->where('statut', StatutDA::EN_COURS_DFC)->count();

        // Montants par catégorie
        $montantAboutis = $baseQuery()->whereIn('statut', $statutsAboutis)->sum('montant');
        $montantEnSuspens = $baseQuery()->where('statut', StatutDA::EN_SUSPENS)->sum('montant');
        $montantCDG = $baseQuery()->where('statut', StatutDA::EN_COURS_CDG)->sum('montant');
        $montantDFC = $baseQuery()->where('statut', StatutDA::EN_COURS_DFC)->sum('montant');

        // Taux d'aboutissement (DA traitées vs total hors en suspens)
        $daEnTraitement = $daEnSuspens + $daCDG + $daDFC;
        $tauxAboutissement = ($daAboutis + $daEnTraitement) > 0
            ? round(($daAboutis / ($daAboutis + $daEnTraitement)) * 100, 1)
            : 0;

        // Délai moyen de traitement
        $delaiMoyen = $baseQuery()
            ->whereIn('statut', $statutsAboutis)
            ->get()
            ->map(fn($da) => $da->date_demande->diffInDays($da->updated_at))
            ->avg();

        // Répartition par statut détaillé
        $repartitionStatuts = $baseQuery()
            ->select('statut', DB::raw('COUNT(*) as nombre'), DB::raw('SUM(montant) as montant'))
            ->groupBy('statut')
            ->get()
            ->map(fn($item) => [
                'statut' => $item->statut,
                'label' => $item->statut instanceof StatutDA ? $item->statut->label() : $item->statut,
                'nombre' => $item->nombre,
                'montant' => $item->montant ?? 0,
            ]);

        return [
            'total' => $daTotal,
            'aboutis' => $daAboutis,
            'en_suspens' => $daEnSuspens,
            'cdg' => $daCDG,
            'dfc' => $daDFC,
            'taux_aboutissement' => $tauxAboutissement,
            'delai_moyen_jours' => $delaiMoyen ? round($delaiMoyen, 1) : null,
            'montants' => [
                'aboutis' => $montantAboutis,
                'en_suspens' => $montantEnSuspens,
                'cdg' => $montantCDG,
                'dfc' => $montantDFC,
            ],
            'repartition_statuts' => $repartitionStatuts,
        ];
    }

    /**
     * Statistiques des DAC (Demandes d'Achat Caisse)
     * Les DAC sont des engagements directs payés à la caisse, sans BC
     */
    protected function getStatistiquesDAC(int $annee): array
    {
        // Filtre de base : uniquement les DAC
        $baseQuery = fn() => DemandeAchat::whereYear('date_demande', $annee)
            ->where('type_demande', 'DAC');

        // Statuts
        $statutsEnCours = [
            StatutDA::EN_SUSPENS,
            StatutDA::EN_COURS_ACH,
            StatutDA::EN_COURS_CDG,
            StatutDA::EN_COURS_DFC,
            StatutDA::EN_COURS_DG,
        ];

        // Compteurs
        $total = $baseQuery()->count();
        $traitees = $baseQuery()->where('statut', StatutDA::TRAITE)->count();
        $cloturees = $baseQuery()->whereNotNull('date_cloture')->count();
        $enCours = $baseQuery()->whereIn('statut', $statutsEnCours)->count();
        $annulees = $baseQuery()->where('statut', StatutDA::ANNULE)->count();

        // Montants
        $montantTraitees = $baseQuery()->where('statut', StatutDA::TRAITE)->sum('montant');
        $montantCloturees = $baseQuery()->whereNotNull('date_cloture')->sum('montant_paye');
        $montantEnCours = $baseQuery()->whereIn('statut', $statutsEnCours)->sum('montant');
        $montantAnnulees = $baseQuery()->where('statut', StatutDA::ANNULE)->sum('montant');

        // Taux d'aboutissement
        $terminees = $traitees + $annulees;
        $tauxAboutissement = $terminees > 0
            ? round(($traitees / $terminees) * 100, 1)
            : 0;

        // Taux de clôture (parmi les traitées)
        $tauxCloture = $traitees > 0
            ? round(($cloturees / $traitees) * 100, 1)
            : 0;

        // Délai moyen de clôture
        $delaiMoyenCloture = $baseQuery()
            ->whereNotNull('date_cloture')
            ->get()
            ->map(fn($dac) => $dac->date_demande->diffInDays($dac->date_cloture))
            ->avg();

        // Répartition par statut
        $repartitionStatuts = $baseQuery()
            ->select('statut', DB::raw('COUNT(*) as nombre'), DB::raw('SUM(montant) as montant'))
            ->groupBy('statut')
            ->get()
            ->map(fn($item) => [
                'statut' => $item->statut,
                'label' => $item->statut instanceof StatutDA ? $item->statut->label() : $item->statut,
                'nombre' => $item->nombre,
                'montant' => $item->montant ?? 0,
            ]);

        return [
            'total' => $total,
            'traitees' => $traitees,
            'cloturees' => $cloturees,
            'en_attente_cloture' => $traitees - $cloturees,
            'en_cours' => $enCours,
            'annulees' => $annulees,
            'taux_aboutissement' => $tauxAboutissement,
            'taux_cloture' => $tauxCloture,
            'delai_moyen_cloture_jours' => $delaiMoyenCloture ? round($delaiMoyenCloture, 1) : null,
            'montants' => [
                'traitees' => $montantTraitees,
                'cloturees' => $montantCloturees,
                'en_cours' => $montantEnCours,
                'annulees' => $montantAnnulees,
            ],
            'repartition_statuts' => $repartitionStatuts,
        ];
    }

    /**
     * Statistiques d'aboutissement des BC
     */
    protected function getStatistiquesBC(int $annee): array
    {
        // Statuts considérés comme "aboutis" (livraison effectuée)
        $statutsAboutis = [StatutBC::LIVRE, StatutBC::TRAITE];

        // Statuts en cours de traitement
        $statutsEnCours = [
            StatutBC::NC,
            StatutBC::EN_COURS_A,
            StatutBC::EN_COURS_CDG,
            StatutBC::EN_COURS_DFC,
            StatutBC::EN_COURS_FSSEUR,
            StatutBC::LIVRAISON_PARTIELLE,
        ];

        // Compteurs par catégorie
        $bcTotal = BonCommande::whereYear('date_bc', $annee)->count();

        $bcAboutis = BonCommande::whereYear('date_bc', $annee)
            ->whereIn('statut', $statutsAboutis)
            ->count();

        $bcEnSuspens = BonCommande::whereYear('date_bc', $annee)
            ->where('statut', StatutBC::NC)
            ->count();

        $bcCDG = BonCommande::whereYear('date_bc', $annee)
            ->where('statut', StatutBC::EN_COURS_CDG)
            ->count();

        $bcDG = BonCommande::whereYear('date_bc', $annee)
            ->where('statut', StatutBC::EN_COURS_DG)
            ->count();

        // Montants par catégorie
        $montantAboutis = BonCommande::whereYear('date_bc', $annee)
            ->whereIn('statut', $statutsAboutis)
            ->sum('montant_ttc_xaf');

        $montantEnSuspens = BonCommande::whereYear('date_bc', $annee)
            ->where('statut', StatutBC::NC)
            ->sum('montant_ttc_xaf');

        $montantCDG = BonCommande::whereYear('date_bc', $annee)
            ->where('statut', StatutBC::EN_COURS_CDG)
            ->sum('montant_ttc_xaf');

        $montantDG = BonCommande::whereYear('date_bc', $annee)
            ->where('statut', StatutBC::EN_COURS_DG)
            ->sum('montant_ttc_xaf');

        // Taux d'aboutissement (BC aboutis vs total en traitement)
        $bcEnTraitement = $bcEnSuspens + $bcCDG + $bcDG;
        $tauxAboutissement = ($bcAboutis + $bcEnTraitement) > 0
            ? round(($bcAboutis / ($bcAboutis + $bcEnTraitement)) * 100, 1)
            : 0;

        // Délai moyen de livraison (entre date_bc et date de dernière réception validée)
        $delaiMoyen = BonCommande::whereYear('date_bc', $annee)
            ->whereIn('statut', $statutsAboutis)
            ->whereHas('receptions', function ($q) {
                $q->where('statut', 'VALIDEE');
            })
            ->get()
            ->map(function ($bc) {
                $derniereReception = $bc->receptions()
                    ->where('statut', 'VALIDEE')
                    ->orderByDesc('date_reception')
                    ->first();

                if ($derniereReception) {
                    return $bc->date_bc->diffInDays($derniereReception->date_reception);
                }
                return null;
            })
            ->filter()
            ->avg();

        // Répartition par statut détaillé
        $repartitionStatuts = BonCommande::whereYear('date_bc', $annee)
            ->select('statut', DB::raw('COUNT(*) as nombre'), DB::raw('SUM(montant_ttc_xaf) as montant'))
            ->groupBy('statut')
            ->get()
            ->map(function ($item) {
                return [
                    'statut' => $item->statut,
                    'label' => $item->statut instanceof StatutBC ? $item->statut->label() : $item->statut,
                    'nombre' => $item->nombre,
                    'montant' => $item->montant ?? 0,
                ];
            });

        return [
            'total' => $bcTotal,
            'aboutis' => $bcAboutis,
            'en_suspens' => $bcEnSuspens,
            'cdg' => $bcCDG,
            'dg' => $bcDG,
            'taux_aboutissement' => $tauxAboutissement,
            'delai_moyen_jours' => $delaiMoyen ? round($delaiMoyen, 1) : null,
            'montants' => [
                'aboutis' => $montantAboutis,
                'en_suspens' => $montantEnSuspens,
                'cdg' => $montantCDG,
                'dg' => $montantDG,
            ],
            'repartition_statuts' => $repartitionStatuts,
        ];
    }

    /**
     * Statistiques des Contrats
     */
    protected function getStatistiquesContrat(int $annee): array
    {
        // Contrats actifs (en cours de validité)
        $contratsActifs = Contrat::where('statut', StatutContrat::ACTIF)
            ->where('date_debut', '<=', now())
            ->where(function ($q) {
                $q->whereNull('date_fin')
                  ->orWhere('date_fin', '>=', now());
            })
            ->count();

        // Montant total annuel des contrats actifs
        $montantAnnuelActif = Contrat::where('statut', StatutContrat::ACTIF)
            ->sum('montant_annuel');

        // Contrats arrivant à échéance (dans les 90 prochains jours)
        $contratsAEcheance = Contrat::where('statut', StatutContrat::ACTIF)
            ->whereNotNull('date_fin')
            ->where('date_fin', '<=', now()->addDays(90))
            ->where('date_fin', '>=', now())
            ->count();

        // Contrats expirés (date_fin passée mais toujours marqués ACTIF)
        $contratsExpires = Contrat::where('statut', StatutContrat::ACTIF)
            ->whereNotNull('date_fin')
            ->where('date_fin', '<', now())
            ->count();

        // Échéances de paiement par statut (pour l'année)
        $echeancesAVenir = EcheanceContrat::where('statut', StatutEcheance::A_VENIR)
            ->whereYear('date_echeance', $annee)
            ->count();

        $echeancesATraiter = EcheanceContrat::where('statut', StatutEcheance::A_TRAITER)
            ->whereYear('date_echeance', $annee)
            ->count();

        $echeancesEnCours = EcheanceContrat::where('statut', StatutEcheance::EN_COURS)
            ->whereYear('date_echeance', $annee)
            ->count();

        $echeancesPayees = EcheanceContrat::where('statut', StatutEcheance::PAYE)
            ->whereYear('date_echeance', $annee)
            ->count();

        // Échéances en retard (A_TRAITER avec date passée)
        $echeancesEnRetard = EcheanceContrat::where('statut', StatutEcheance::A_TRAITER)
            ->where('date_echeance', '<', now())
            ->count();

        // Montants des échéances
        $montantEcheancesAVenir = EcheanceContrat::where('statut', StatutEcheance::A_VENIR)
            ->whereYear('date_echeance', $annee)
            ->sum('montant_prevu');

        $montantEcheancesATraiter = EcheanceContrat::where('statut', StatutEcheance::A_TRAITER)
            ->whereYear('date_echeance', $annee)
            ->sum('montant_prevu');

        $montantEcheancesPayees = EcheanceContrat::where('statut', StatutEcheance::PAYE)
            ->whereYear('date_echeance', $annee)
            ->sum('montant_prevu');

        $montantEcheancesEnRetard = EcheanceContrat::where('statut', StatutEcheance::A_TRAITER)
            ->where('date_echeance', '<', now())
            ->sum('montant_prevu');

        // Top types de contrats par montant
        $topTypesContrat = Contrat::join('types_contrat', 'contrats.type_contrat_id', '=', 'types_contrat.id')
            ->where('contrats.statut', StatutContrat::ACTIF)
            ->selectRaw('types_contrat.libelle, COUNT(*) as nombre, SUM(contrats.montant_annuel) as montant')
            ->groupBy('types_contrat.id', 'types_contrat.libelle')
            ->orderByDesc('montant')
            ->limit(5)
            ->get();

        return [
            'contrats_actifs' => $contratsActifs,
            'contrats_a_echeance' => $contratsAEcheance,
            'contrats_expires' => $contratsExpires,
            'montant_annuel_actif' => $montantAnnuelActif,
            'echeances' => [
                'a_venir' => $echeancesAVenir,
                'a_traiter' => $echeancesATraiter,
                'en_cours' => $echeancesEnCours,
                'payees' => $echeancesPayees,
                'en_retard' => $echeancesEnRetard,
            ],
            'montants_echeances' => [
                'a_venir' => $montantEcheancesAVenir,
                'a_traiter' => $montantEcheancesATraiter,
                'payees' => $montantEcheancesPayees,
                'en_retard' => $montantEcheancesEnRetard,
            ],
            'top_types_contrat' => $topTypesContrat,
        ];
    }
}
