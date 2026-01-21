<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class ConvertImportFile extends Command
{
    protected $signature = 'import:convert
                            {source : Chemin du fichier source (CSV ou Excel)}
                            {--output-dir=storage/app/imports : Dossier de sortie}
                            {--format=xlsx : Format de sortie (xlsx ou csv)}';

    protected $description = 'Convertit un fichier source combiné en fichiers d\'import séparés (EB, DA, DAC, BC)';

    // Mapping des statuts EB/DA
    protected array $statutEbDaMapping = [
        'OK / En cours ACH' => 'EN_COURS_ACH',
        'OK / En cours CDG' => 'EN_COURS_CDG',
        'OK / En cours DFC' => 'EN_COURS_DFC',
        'OK / En cours DG' => 'EN_COURS_DG',
        'OK / Traitée' => 'TRAITE',
        'OK / Trait' => 'TRAITE',
        'En cours ACH' => 'EN_COURS_ACH',
        'En cours CDG' => 'EN_COURS_CDG',
        'En cours DFC' => 'EN_COURS_DFC',
        'EN_SUSPENS' => 'EN_SUSPENS',
    ];

    // Mapping des statuts BC
    protected array $statutBcMapping = [
        'N/C' => 'NC',
        'NC' => 'NC',
        'En Cours A' => 'EN_COURS_A',
        'En Cours CDG' => 'EN_COURS_CDG',
        'En Cours DFC' => 'EN_COURS_DFC',
        'En Cours DG' => 'EN_COURS_DG',
        'En cours F/sseur' => 'EN_COURS_FSSEUR',
        'DAC CDG' => 'DAC_CDG',
        'DAC DG' => 'DAC_DG',
        'DAC Tréso' => 'DAC_TRESO',
        'DAC Tr' => 'DAC_TRESO',
        'Livré' => 'LIVRE',
        'Livr' => 'LIVRE',
        'Livraison Partielle' => 'LIVRAISON_PARTIELLE',
        'Traité' => 'TRAITE',
        'Trait' => 'TRAITE',
        'Annulé' => 'ANNULE',
    ];

    // Mapping des acheteurs (prénom -> matricule)
    protected array $acheteurMapping = [
        'Ferlez' => 'ACH001',
        'Judith' => 'ACH002',
        'Leroy' => 'ACH003',
        'Wilfride' => 'ACH004',
    ];

    // Mapping des fournisseurs (nom source -> code)
    protected array $fournisseurMapping = [
        'Total E' => 'TOTAL-E',
        'Sporafric' => 'SPORAFRIC',
        'Ste GTEC' => 'GTEC',
        'Wecom' => 'WECOM',
        'Burotec' => 'BUROTEC',
        'Transit Express' => 'TRANSIT-EXP',
        'Satguru' => 'SATGURU',
        'Hariom' => 'HARIOM',
        'Aerco' => 'AERCO',
        'Visiona' => 'VISIONA',
        'E2C' => 'E2C',
        'SCI Rivière Rouge' => 'SCI-RR',
        'SCI Rivi�re Rouge' => 'SCI-RR',
        'Tresor Public' => 'TRESOR',
        'EDT' => 'EDT',
        'Maitre Otieli' => 'OTIELI',
        'LCR' => 'LCR',
        'Durel Services' => 'DUREL',
        'Trans Bony' => 'TRANS-BONY',
        'Crisp n croc' => 'CRISP',
        'Divers' => 'DIVERS',
    ];

    public function handle()
    {
        $sourcePath = $this->argument('source');
        $outputDir = $this->option('output-dir');
        $format = $this->option('format');

        // Vérifier que le fichier source existe
        if (!file_exists($sourcePath)) {
            $this->error("Fichier source non trouvé: {$sourcePath}");
            return 1;
        }

        $this->info("Lecture du fichier source: {$sourcePath}");

        // Lire le fichier source
        $data = $this->readSourceFile($sourcePath);

        if (empty($data)) {
            $this->error("Aucune donnée trouvée dans le fichier source");
            return 1;
        }

        $this->info("Nombre de lignes lues: " . count($data));

        // Séparer les données par module
        $ebData = [];
        $daData = [];
        $dacData = [];
        $bcData = [];

        foreach ($data as $row) {
            // Détecter le type de ligne
            $hasEB = !empty(trim($row['n_eb'] ?? ''));
            $hasDA = !empty(trim($row['n_da'] ?? ''));
            $hasBC = !empty(trim($row['n_bc'] ?? '')) && trim($row['n_bc']) !== '0';

            // Déterminer si c'est une DAC (basé sur le N° DA contenant DAC)
            $isDAC = $hasDA && stripos($row['n_da'] ?? '', 'DAC') !== false;

            // Ajouter aux listes appropriées
            if ($hasEB && !$hasDA && !$hasBC) {
                // EB seul (pas encore transformé)
                $ebData[] = $this->transformToEB($row);
            }

            if ($hasDA && !$isDAC) {
                // DA classique
                $daData[] = $this->transformToDA($row, 'DA');
            }

            if ($isDAC) {
                // DAC
                $dacData[] = $this->transformToDA($row, 'DAC');
            }

            if ($hasBC) {
                // BC
                $bcData[] = $this->transformToBC($row);
            }
        }

        // Créer le dossier de sortie
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $timestamp = date('Y-m-d_His');

        // Générer les fichiers
        $files = [];

        if (!empty($ebData)) {
            $ebFile = $this->generateFile($ebData, $outputDir, "import_eb_{$timestamp}", $format, 'EB');
            $files['EB'] = $ebFile;
            $this->info("Fichier EB généré: {$ebFile} (" . count($ebData) . " lignes)");
        }

        if (!empty($daData)) {
            $daFile = $this->generateFile($daData, $outputDir, "import_da_{$timestamp}", $format, 'DA');
            $files['DA'] = $daFile;
            $this->info("Fichier DA généré: {$daFile} (" . count($daData) . " lignes)");
        }

        if (!empty($dacData)) {
            $dacFile = $this->generateFile($dacData, $outputDir, "import_dac_{$timestamp}", $format, 'DAC');
            $files['DAC'] = $dacFile;
            $this->info("Fichier DAC généré: {$dacFile} (" . count($dacData) . " lignes)");
        }

        if (!empty($bcData)) {
            $bcFile = $this->generateFile($bcData, $outputDir, "import_bc_{$timestamp}", $format, 'BC');
            $files['BC'] = $bcFile;
            $this->info("Fichier BC généré: {$bcFile} (" . count($bcData) . " lignes)");
        }

        $this->newLine();
        $this->info("Conversion terminée!");
        $this->table(['Module', 'Fichier', 'Lignes'], [
            ['EB', $files['EB'] ?? '-', count($ebData)],
            ['DA', $files['DA'] ?? '-', count($daData)],
            ['DAC', $files['DAC'] ?? '-', count($dacData)],
            ['BC', $files['BC'] ?? '-', count($bcData)],
        ]);

        return 0;
    }

    protected function readSourceFile(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $data = [];

        if ($extension === 'csv') {
            $handle = fopen($path, 'r');
            // Détecter le séparateur
            $firstLine = fgets($handle);
            rewind($handle);
            $separator = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

            $headers = null;
            while (($row = fgetcsv($handle, 0, $separator)) !== false) {
                if ($headers === null) {
                    // Normaliser les headers
                    $headers = array_map(fn($h) => $this->normalizeHeader($h), $row);
                    continue;
                }

                if (count($row) === count($headers)) {
                    $data[] = array_combine($headers, $row);
                }
            }
            fclose($handle);
        } else {
            // Excel
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            $headers = array_map(fn($h) => $this->normalizeHeader($h ?? ''), array_shift($rows));

            foreach ($rows as $row) {
                if (count($row) === count($headers)) {
                    $data[] = array_combine($headers, $row);
                }
            }
        }

        return $data;
    }

    protected function normalizeHeader(string $header): string
    {
        // Convertir en minuscules et remplacer les caractères spéciaux
        $header = mb_strtolower(trim($header));
        $header = str_replace(['°', ' ', '-', '/'], ['', '_', '_', '_'], $header);
        $header = preg_replace('/[^a-z0-9_]/', '', $header);
        return $header;
    }

    protected function transformToEB(array $row): array
    {
        $statut = $this->mapStatutEbDa($row['statut_ebda'] ?? $row['statut_eb_da'] ?? '');

        return [
            'zone_code' => '', // Optionnel
            'direction_code' => trim($row['direction'] ?? ''),
            'service_code' => trim($row['service'] ?? ''),
            'demandeur_nom' => trim($row['demandeur'] ?? ''),
            'objet' => trim($row['description_du_besoin_et_quantit_souhaite'] ?? $row['description_du_besoin'] ?? ''),
            'description_detaillee' => '',
            'quantite_souhaitee' => '',
            'date_besoin_jjmmaaaa' => $this->formatDate($row['date_eb'] ?? ''),
            'estimation_fcfa' => $this->cleanNumber($row['estimation'] ?? '0'),
            'acheteur_matricule' => $this->mapAcheteur($row['acheteur'] ?? ''),
            'fournisseur_code' => $this->mapFournisseur($row['fournisseur'] ?? ''),
            'statut' => $statut,
            'commentaire' => '',
        ];
    }

    protected function transformToDA(array $row, string $type): array
    {
        $statut = $this->mapStatutEbDa($row['statut_ebda'] ?? $row['statut_eb_da'] ?? '');

        return [
            'type_demande_da_ou_dac' => $type,
            'zone_code' => '',
            'direction_code' => trim($row['direction'] ?? ''),
            'service_code' => trim($row['service'] ?? ''),
            'objet' => trim($row['description_da'] ?? $row['description_du_besoin_et_quantit_souhaite'] ?? ''),
            'description' => '',
            'montant_fcfa' => $this->cleanNumber($row['montant'] ?? '0'),
            'acheteur_matricule' => $this->mapAcheteur($row['acheteur'] ?? ''),
            'statut' => $statut,
            'commentaire' => '',
        ];
    }

    protected function transformToBC(array $row): array
    {
        $statut = $this->mapStatutBc($row['statut_bc'] ?? '');

        // Déterminer le type de BC basé sur le contexte
        $typeBC = 'BCAL'; // Défaut

        return [
            'type_bc_bcalbclbcaibciipo' => $typeBC,
            'fournisseur_code' => $this->mapFournisseur($row['fournisseur'] ?? ''),
            'zone_code' => '',
            'direction_code' => trim($row['direction'] ?? ''),
            'objet' => trim($row['description_bc'] ?? $row['description_da'] ?? ''),
            'nature_prestation' => '',
            'montant_ht_fcfa' => $this->cleanNumber($row['montant'] ?? '0'),
            'taux_tva' => '19.25',
            'date_livraison_prevue_jjmmaaaa' => '',
            'acheteur_matricule' => $this->mapAcheteur($row['acheteur'] ?? ''),
            'conditions_paiement' => 'A reception',
            'adresse_livraison' => '',
            'numero_devis' => '',
            'statut' => $statut,
            'commentaire' => '',
        ];
    }

    protected function mapStatutEbDa(string $statut): string
    {
        $statut = trim($statut);
        foreach ($this->statutEbDaMapping as $pattern => $mapped) {
            if (stripos($statut, $pattern) !== false || $statut === $pattern) {
                return $mapped;
            }
        }
        return 'EN_SUSPENS';
    }

    protected function mapStatutBc(string $statut): string
    {
        $statut = trim($statut);
        foreach ($this->statutBcMapping as $pattern => $mapped) {
            if (stripos($statut, $pattern) !== false || $statut === $pattern) {
                return $mapped;
            }
        }
        return 'NC';
    }

    protected function mapAcheteur(string $nom): string
    {
        $nom = trim($nom);
        return $this->acheteurMapping[$nom] ?? $nom;
    }

    protected function mapFournisseur(string $nom): string
    {
        $nom = trim($nom);
        return $this->fournisseurMapping[$nom] ?? $nom;
    }

    protected function cleanNumber(string $value): string
    {
        // Supprimer les espaces et remplacer les virgules par des points
        $value = str_replace([' ', ','], ['', '.'], trim($value));
        return is_numeric($value) ? $value : '0';
    }

    protected function formatDate(string $date): string
    {
        if (empty($date)) {
            return '';
        }

        // Si c'est un nombre Excel (serial date)
        if (is_numeric($date)) {
            try {
                $unixDate = ($date - 25569) * 86400;
                return date('d/m/Y', $unixDate);
            } catch (\Exception $e) {
                return '';
            }
        }

        // Sinon essayer de parser
        try {
            $parsed = \Carbon\Carbon::parse($date);
            return $parsed->format('d/m/Y');
        } catch (\Exception $e) {
            return $date;
        }
    }

    protected function generateFile(array $data, string $outputDir, string $filename, string $format, string $type): string
    {
        $headers = $this->getHeaders($type);
        $fullPath = "{$outputDir}/{$filename}.{$format}";

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Écrire les headers
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col++, 1, $header);
        }

        // Écrire les données
        $rowNum = 2;
        foreach ($data as $row) {
            $col = 1;
            foreach ($headers as $header) {
                $sheet->setCellValueByColumnAndRow($col++, $rowNum, $row[$header] ?? '');
            }
            $rowNum++;
        }

        // Sauvegarder
        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(';');
        } else {
            $writer = new Xlsx($spreadsheet);
        }

        $writer->save($fullPath);

        return $fullPath;
    }

    protected function getHeaders(string $type): array
    {
        return match ($type) {
            'EB' => [
                'zone_code', 'direction_code', 'service_code', 'demandeur_nom',
                'objet', 'description_detaillee', 'quantite_souhaitee',
                'date_besoin_jjmmaaaa', 'estimation_fcfa', 'acheteur_matricule',
                'fournisseur_code', 'statut', 'commentaire'
            ],
            'DA', 'DAC' => [
                'type_demande_da_ou_dac', 'zone_code', 'direction_code', 'service_code',
                'objet', 'description', 'montant_fcfa', 'acheteur_matricule',
                'statut', 'commentaire'
            ],
            'BC' => [
                'type_bc_bcalbclbcaibciipo', 'fournisseur_code', 'zone_code',
                'direction_code', 'objet', 'nature_prestation', 'montant_ht_fcfa',
                'taux_tva', 'date_livraison_prevue_jjmmaaaa', 'acheteur_matricule',
                'conditions_paiement', 'adresse_livraison', 'numero_devis',
                'statut', 'commentaire'
            ],
            default => []
        };
    }
}
