<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\ExpressionBesoinImport;
use App\Imports\DemandeAchatImport;
use App\Imports\BonCommandeImport;
use App\Imports\EngagementsConsolidesImport;
use App\Exports\ExpressionBesoinTemplateExport;
use App\Exports\DemandeAchatTemplateExport;
use App\Exports\BonCommandeTemplateExport;
use App\Services\NumerotationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    protected NumerotationService $numerotation;

    public function __construct(NumerotationService $numerotation)
    {
        $this->numerotation = $numerotation;
    }

    /**
     * Télécharger le template Excel pour les Expressions de Besoins
     */
    public function templateEB()
    {
        return Excel::download(
            new ExpressionBesoinTemplateExport(),
            'template_expressions_besoin.xlsx'
        );
    }

    /**
     * Télécharger le template Excel pour les Demandes d'Achat
     */
    public function templateDA()
    {
        return Excel::download(
            new DemandeAchatTemplateExport(),
            'template_demandes_achat.xlsx'
        );
    }

    /**
     * Télécharger le template Excel pour les Bons de Commande
     */
    public function templateBC()
    {
        return Excel::download(
            new BonCommandeTemplateExport(),
            'template_bons_commande.xlsx'
        );
    }

    /**
     * Importer des Expressions de Besoins depuis un fichier Excel
     */
    public function importEB(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $import = new ExpressionBesoinImport($this->numerotation);

            DB::beginTransaction();
            Excel::import($import, $request->file('file'));
            DB::commit();

            $failures = $import->failures();
            $errors = $import->getErrors();

            $response = [
                'message' => 'Import termine',
                'total_lignes' => $import->getRowCount(),
                'crees' => $import->getCreatedCount(),
                'mis_a_jour' => $import->getUpdatedCount(),
                'succes' => $import->getCreatedCount() + $import->getUpdatedCount(),
                'erreurs' => [],
            ];

            // Collecter les erreurs de validation
            foreach ($failures as $failure) {
                $response['erreurs'][] = [
                    'ligne' => $failure->row(),
                    'champ' => $failure->attribute(),
                    'messages' => $failure->errors(),
                ];
            }

            // Ajouter les erreurs métier
            foreach ($errors as $error) {
                $response['erreurs'][] = [
                    'ligne' => 0,
                    'champ' => 'general',
                    'messages' => [$error],
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur import EB: ' . $e->getMessage());

            return response()->json([
                'message' => 'Erreur lors de l\'import',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Importer des Demandes d'Achat depuis un fichier Excel
     */
    public function importDA(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $import = new DemandeAchatImport($this->numerotation);

            DB::beginTransaction();
            Excel::import($import, $request->file('file'));
            DB::commit();

            $failures = $import->failures();
            $errors = $import->getErrors();

            $response = [
                'message' => 'Import termine',
                'total_lignes' => $import->getRowCount(),
                'crees' => $import->getCreatedCount(),
                'mis_a_jour' => $import->getUpdatedCount(),
                'succes' => $import->getCreatedCount() + $import->getUpdatedCount(),
                'erreurs' => [],
            ];

            foreach ($failures as $failure) {
                $response['erreurs'][] = [
                    'ligne' => $failure->row(),
                    'champ' => $failure->attribute(),
                    'messages' => $failure->errors(),
                ];
            }

            foreach ($errors as $error) {
                $response['erreurs'][] = [
                    'ligne' => 0,
                    'champ' => 'general',
                    'messages' => [$error],
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur import DA: ' . $e->getMessage());

            return response()->json([
                'message' => 'Erreur lors de l\'import',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Importer des Bons de Commande depuis un fichier Excel
     */
    public function importBC(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $import = new BonCommandeImport($this->numerotation);

            DB::beginTransaction();
            Excel::import($import, $request->file('file'));
            DB::commit();

            $failures = $import->failures();
            $errors = $import->getErrors();

            $response = [
                'message' => 'Import termine',
                'total_lignes' => $import->getRowCount(),
                'crees' => $import->getCreatedCount(),
                'mis_a_jour' => $import->getUpdatedCount(),
                'succes' => $import->getCreatedCount() + $import->getUpdatedCount(),
                'erreurs' => [],
            ];

            foreach ($failures as $failure) {
                $response['erreurs'][] = [
                    'ligne' => $failure->row(),
                    'champ' => $failure->attribute(),
                    'messages' => $failure->errors(),
                ];
            }

            foreach ($errors as $error) {
                $response['erreurs'][] = [
                    'ligne' => 0,
                    'champ' => 'general',
                    'messages' => [$error],
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur import BC: ' . $e->getMessage());

            return response()->json([
                'message' => 'Erreur lors de l\'import',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Importer des Engagements depuis un fichier CSV consolidé
     * Ce format permet d'importer EB, DA et BC en une seule fois
     * avec les liens entre eux
     */
    public function importEngagements(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        try {
            $import = new EngagementsConsolidesImport($this->numerotation);

            DB::beginTransaction();
            Excel::import($import, $request->file('file'));
            DB::commit();

            $failures = $import->failures();
            $errors = $import->getErrors();
            $stats = $import->getStats();

            $totalCrees = $stats['eb_crees'] + $stats['da_crees'] + $stats['bc_crees'];
            $totalMaj = $stats['eb_mis_a_jour'] + $stats['da_mis_a_jour'] + $stats['bc_mis_a_jour'];

            $response = [
                'message' => 'Import des engagements termine',
                'total_lignes' => $import->getRowCount(),
                'stats' => $stats,
                'crees' => $totalCrees,
                'mis_a_jour' => $totalMaj,
                'succes' => $totalCrees + $totalMaj,
                'erreurs' => [],
            ];

            foreach ($failures as $failure) {
                $response['erreurs'][] = [
                    'ligne' => $failure->row(),
                    'champ' => $failure->attribute(),
                    'messages' => $failure->errors(),
                ];
            }

            foreach ($errors as $error) {
                $response['erreurs'][] = [
                    'ligne' => 0,
                    'champ' => 'general',
                    'messages' => [$error],
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur import engagements: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return response()->json([
                'message' => 'Erreur lors de l\'import',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
