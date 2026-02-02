<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ZoneController;
use App\Http\Controllers\Api\DirectionController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\FournisseurController;
use App\Http\Controllers\Api\ExpressionBesoinController;
use App\Http\Controllers\Api\DemandeAchatController;
use App\Http\Controllers\Api\BonCommandeController;
use App\Http\Controllers\Api\ReceptionController;
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StatistiqueController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UniteMesureController;
use App\Http\Controllers\Api\NatureDepenseController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\LigneDemandeAchatController;
use App\Http\Controllers\Api\OffreFournisseurController;
use App\Http\Controllers\Api\CriterePonderationController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\TypeContratController;
use App\Http\Controllers\Api\ContratController;
use App\Http\Controllers\Api\EcheanceContratController;

/*
|--------------------------------------------------------------------------
| API Routes - CT_Achats
|--------------------------------------------------------------------------
*/

// Health check pour Electron (route publique sans préfixe v1)
Route::get('/v1/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'app' => config('app.name'),
    ]);
});

// Routes publiques
Route::prefix('v1')->group(function () {
    // Authentification
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Routes protégées
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    
    // Utilisateur connecté
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Organisation
    Route::apiResource('zones', ZoneController::class);
    Route::apiResource('directions', DirectionController::class);
    Route::apiResource('services', ServiceController::class);
    
    // Utilisateurs
    Route::get('/users/acheteurs', [UserController::class, 'acheteurs']);
    Route::get('/users/valideurs', [UserController::class, 'valideurs']);
    Route::apiResource('users', UserController::class);

    // Fournisseurs
    Route::apiResource('fournisseurs', FournisseurController::class);
    Route::get('/fournisseurs/{fournisseur}/contacts', [FournisseurController::class, 'contacts']);
    Route::post('/fournisseurs/{fournisseur}/contacts', [FournisseurController::class, 'storeContact']);
    Route::get('/fournisseurs/{fournisseur}/documents', [FournisseurController::class, 'documents']);
    Route::post('/fournisseurs/{fournisseur}/documents', [FournisseurController::class, 'storeDocument']);

    // Expressions de Besoins
    Route::apiResource('expressions-besoin', ExpressionBesoinController::class);
    Route::post('/expressions-besoin/{eb}/assigner', [ExpressionBesoinController::class, 'assigner']);
    Route::post('/expressions-besoin/{eb}/valider', [ExpressionBesoinController::class, 'valider']);
    Route::post('/expressions-besoin/{eb}/rejeter', [ExpressionBesoinController::class, 'rejeter']);
    Route::post('/expressions-besoin/{eb}/transformer', [ExpressionBesoinController::class, 'transformer']);

    // Demandes d'Achat
    Route::apiResource('demandes-achat', DemandeAchatController::class);
    Route::post('/demandes-achat/{da}/valider', [DemandeAchatController::class, 'valider']);
    Route::post('/demandes-achat/{da}/rejeter', [DemandeAchatController::class, 'rejeter']);
    Route::post('/demandes-achat/{da}/transformer', [DemandeAchatController::class, 'transformer']);
    Route::post('/demandes-achat/{da}/cloturer-dac', [DemandeAchatController::class, 'cloturerDac']);

    // Lignes de Demandes d'Achat
    Route::prefix('demandes-achat/{da}/lignes')->group(function () {
        Route::get('/', [LigneDemandeAchatController::class, 'index']);
        Route::post('/', [LigneDemandeAchatController::class, 'store']);
        Route::get('/{ligne}', [LigneDemandeAchatController::class, 'show']);
        Route::put('/{ligne}', [LigneDemandeAchatController::class, 'update']);
        Route::delete('/{ligne}', [LigneDemandeAchatController::class, 'destroy']);
        Route::post('/{ligne}/select-offre', [LigneDemandeAchatController::class, 'selectOffer']);
    });

    // Offres Fournisseur (pour une ligne de DA)
    Route::prefix('lignes-da/{ligne}/offres')->group(function () {
        Route::get('/', [OffreFournisseurController::class, 'index']);
        Route::post('/', [OffreFournisseurController::class, 'store']);
        Route::get('/compare', [OffreFournisseurController::class, 'compare']);
        Route::get('/{offre}', [OffreFournisseurController::class, 'show']);
        Route::put('/{offre}', [OffreFournisseurController::class, 'update']);
        Route::delete('/{offre}', [OffreFournisseurController::class, 'destroy']);
    });

    // Critères de pondération
    Route::prefix('criteres-ponderation')->group(function () {
        Route::get('/', [CriterePonderationController::class, 'index']);
        Route::put('/poids', [CriterePonderationController::class, 'updatePoids']);
        Route::post('/{critere}/toggle', [CriterePonderationController::class, 'toggle']);
    });

    // Bons de Commande
    Route::apiResource('bons-commande', BonCommandeController::class);
    Route::post('/bons-commande/{bc}/valider', [BonCommandeController::class, 'valider']);
    Route::post('/bons-commande/{bc}/rejeter', [BonCommandeController::class, 'rejeter']);
    Route::post('/bons-commande/{bc}/envoyer', [BonCommandeController::class, 'envoyer']);
    Route::get('/bons-commande/{bc}/pdf', [BonCommandeController::class, 'genererPdf']);
    Route::get('/bons-commande/{bc}/lignes-pour-reception', [ReceptionController::class, 'lignesBonCommande']);

    // Réceptions (Bons de Réception)
    Route::apiResource('receptions', ReceptionController::class);
    Route::post('/receptions/{reception}/valider', [ReceptionController::class, 'valider']);

    // Factures
    Route::apiResource('factures', FactureController::class);
    Route::post('/factures/{facture}/soumettre', [FactureController::class, 'soumettre']);
    Route::post('/factures/{facture}/valider', [FactureController::class, 'valider']);
    Route::post('/factures/{facture}/rejeter', [FactureController::class, 'rejeter']);
    Route::post('/factures/{facture}/paiement', [FactureController::class, 'enregistrerPaiement']);
    Route::get('/factures-stats', [FactureController::class, 'statistiques']);

    // Contrats
    Route::apiResource('types-contrat', TypeContratController::class);
    Route::apiResource('contrats', ContratController::class);
    Route::post('/contrats/{contrat}/activer', [ContratController::class, 'activer']);
    Route::post('/contrats/{contrat}/suspendre', [ContratController::class, 'suspendre']);
    Route::post('/contrats/{contrat}/reactiver', [ContratController::class, 'reactiver']);
    Route::post('/contrats/{contrat}/terminer', [ContratController::class, 'terminer']);
    Route::post('/contrats/{contrat}/resilier', [ContratController::class, 'resilier']);
    Route::post('/contrats/{contrat}/generer-echeances', [ContratController::class, 'genererEcheances']);
    Route::get('/contrats/{contrat}/echeances', [ContratController::class, 'echeances']);
    Route::get('/contrats-stats', [ContratController::class, 'statistiques']);

    // Echeances de contrats
    Route::apiResource('echeances-contrat', EcheanceContratController::class)->only(['index', 'show', 'update']);
    Route::post('/echeances-contrat/{echeance}/facture', [EcheanceContratController::class, 'enregistrerFacture']);
    Route::post('/echeances-contrat/{echeance}/payer', [EcheanceContratController::class, 'marquerPayee']);
    Route::post('/echeances-contrat/{echeance}/annuler', [EcheanceContratController::class, 'annuler']);
    Route::post('/echeances-contrat/{echeance}/a-traiter', [EcheanceContratController::class, 'aTraiter']);
    Route::get('/echeances-contrat-stats', [EcheanceContratController::class, 'statistiques']);
    Route::get('/echeances-contrat-calendrier', [EcheanceContratController::class, 'calendrier']);

    // Statistiques complètes
    Route::prefix('statistiques')->group(function () {
        Route::get('/overview', [StatistiqueController::class, 'overview']);
        Route::get('/evolution', [StatistiqueController::class, 'evolutionMensuelle']);
        Route::get('/fournisseurs', [StatistiqueController::class, 'topFournisseurs']);
        Route::get('/directions', [StatistiqueController::class, 'parDirection']);
        Route::get('/delais', [StatistiqueController::class, 'delais']);
        Route::get('/alertes', [StatistiqueController::class, 'alertes']);
        Route::get('/annees', [StatistiqueController::class, 'anneesDisponibles']);
    });

    // Paramètres système
    Route::prefix('settings')->group(function () {
        Route::get('/general', [SettingsController::class, 'general']);
        Route::put('/general', [SettingsController::class, 'updateGeneral']);
        Route::post('/logo', [SettingsController::class, 'uploadLogo']);
        Route::delete('/logo', [SettingsController::class, 'deleteLogo']);
        Route::get('/seuils', [SettingsController::class, 'seuils']);
        Route::put('/seuils', [SettingsController::class, 'updateSeuils']);
        Route::get('/numerotation', [SettingsController::class, 'numerotation']);
        Route::put('/numerotation', [SettingsController::class, 'updateNumerotation']);
    });

    // Référentiels CRUD
    Route::apiResource('unites-mesure', UniteMesureController::class);
    Route::apiResource('natures-depense', NatureDepenseController::class);
    Route::apiResource('roles', RoleController::class);

    // Listes de référence (select/dropdown)
    Route::prefix('referentiels')->group(function () {
        Route::get('/zones', [ZoneController::class, 'liste']);
        Route::get('/directions', [DirectionController::class, 'liste']);
        Route::get('/services', [ServiceController::class, 'liste']);
        Route::get('/acheteurs', [UserController::class, 'listeAcheteurs']);
        Route::get('/fournisseurs', [FournisseurController::class, 'liste']);
        Route::get('/unites-mesure', [DemandeAchatController::class, 'listeUnites']);
        Route::get('/natures-depense', [DemandeAchatController::class, 'listeNatures']);
        Route::get('/statuts-eb', [ExpressionBesoinController::class, 'listeStatuts']);
        Route::get('/statuts-da', [DemandeAchatController::class, 'listeStatuts']);
        Route::get('/statuts-bc', [BonCommandeController::class, 'listeStatuts']);
        Route::get('/statuts-br', [ReceptionController::class, 'listeStatuts']);
        Route::get('/statuts-facture', [FactureController::class, 'listeStatuts']);
        Route::get('/statuts-paiement', [FactureController::class, 'listeStatutsPaiement']);
        Route::get('/types-facture', [FactureController::class, 'listeTypes']);
        Route::get('/types-bc', [BonCommandeController::class, 'listeTypes']);
        Route::get('/conditions-paiement', [BonCommandeController::class, 'conditionsPaiement']);
        Route::get('/types-contrat', [TypeContratController::class, 'index']);
        Route::get('/statuts-contrat', [ContratController::class, 'listeStatuts']);
        Route::get('/periodicites-contrat', [ContratController::class, 'listePeriodicites']);
        Route::get('/statuts-echeance', [EcheanceContratController::class, 'listeStatuts']);
    });

    // Import de masse et templates Excel
    Route::prefix('import')->group(function () {
        // Téléchargement des templates
        Route::get('/template/eb', [ImportController::class, 'templateEB']);
        Route::get('/template/da', [ImportController::class, 'templateDA']);
        Route::get('/template/bc', [ImportController::class, 'templateBC']);
        // Import des fichiers
        Route::post('/eb', [ImportController::class, 'importEB']);
        Route::post('/da', [ImportController::class, 'importDA']);
        Route::post('/bc', [ImportController::class, 'importBC']);
        // Import consolidé (EB + DA + BC en une seule fois)
        Route::post('/engagements', [ImportController::class, 'importEngagements']);
    });
});
