<?php

namespace App\Imports;

use App\Models\ExpressionBesoin;
use App\Models\DemandeAchat;
use App\Models\BonCommande;
use App\Models\Zone;
use App\Models\Direction;
use App\Models\Service;
use App\Models\User;
use App\Models\Fournisseur;
use App\Services\NumerotationService;
use App\Enums\StatutEB;
use App\Enums\StatutDA;
use App\Enums\StatutBC;
use App\Enums\TypeDemande;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EngagementsConsolidesImport implements ToModel, WithHeadingRow, SkipsEmptyRows, SkipsOnFailure, WithCustomCsvSettings
{
    use SkipsFailures;

    protected NumerotationService $numerotation;
    protected string $societeId;
    protected ?string $defaultZoneId;
    protected int $rowCount = 0;
    protected int $ebCreated = 0;
    protected int $ebUpdated = 0;
    protected int $daCreated = 0;
    protected int $daUpdated = 0;
    protected int $bcCreated = 0;
    protected int $bcUpdated = 0;
    protected array $errors = [];

    // Cache pour les lookups
    protected array $directionsCache = [];
    protected array $servicesCache = [];
    protected array $acheteursCache = [];
    protected array $fournisseursCache = [];

    public function __construct(NumerotationService $numerotation)
    {
        $this->numerotation = $numerotation;
        $user = Auth::user();
        $this->societeId = $user->service->direction->zone->societe_id;
        $this->defaultZoneId = $user->service->direction->zone_id;

        // Pre-charger les caches
        $this->loadCaches();
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'enclosure' => '"',
            'input_encoding' => 'Windows-1252',
        ];
    }

    protected function loadCaches(): void
    {
        // Charger les directions
        $directions = Direction::where('actif', true)->get();
        foreach ($directions as $dir) {
            $this->directionsCache[strtoupper($dir->code)] = $dir;
            $this->directionsCache[strtoupper($dir->libelle_court)] = $dir;
        }

        // Alias courants pour les directions (anciens codes ou abréviations)
        $aliases = [
            'HSE' => 'QHSE', // HSE -> QHSE
            'DIRGEN' => 'DG',
            'DAF' => 'DFC',  // Direction Administrative et Financière -> DFC
            'DRH' => 'DARH', // DRH -> DARH
            'COMM' => 'DMTD', // Communication -> DMTD
            'MKT' => 'DMTD', // Marketing -> DMTD
        ];
        foreach ($aliases as $alias => $target) {
            if (isset($this->directionsCache[$target]) && !isset($this->directionsCache[$alias])) {
                $this->directionsCache[$alias] = $this->directionsCache[$target];
            }
        }

        // Charger les services
        $services = Service::where('actif', true)->get();
        foreach ($services as $svc) {
            $key = strtoupper(trim($svc->libelle));
            $this->servicesCache[$key] = $svc;
        }

        // Charger les acheteurs
        $acheteurs = User::where('est_acheteur', true)->where('actif', true)->get();
        foreach ($acheteurs as $ach) {
            $this->acheteursCache[strtoupper($ach->prenom)] = $ach;
            $this->acheteursCache[strtoupper($ach->nom)] = $ach;
            $this->acheteursCache[strtoupper($ach->prenom . ' ' . $ach->nom)] = $ach;
        }

        // Charger les fournisseurs
        $fournisseurs = Fournisseur::where('statut', 'ACTIF')->get();
        foreach ($fournisseurs as $four) {
            $this->fournisseursCache[strtoupper(trim($four->raison_sociale))] = $four;
            if ($four->code) {
                $this->fournisseursCache[strtoupper(trim($four->code))] = $four;
            }
        }
    }

    public function model(array $row)
    {
        $this->rowCount++;

        // Normaliser les clés (les en-têtes peuvent avoir des caractères spéciaux)
        $row = $this->normalizeRowKeys($row);

        // Extraire les références
        $numEB = $this->cleanReference($row['n_eb'] ?? $row['no_eb'] ?? $row['numero_eb'] ?? null);
        $numDA = $this->cleanReference($row['n_da'] ?? $row['no_da'] ?? $row['numero_da'] ?? null);
        $numBC = $this->cleanReference($row['n_bc'] ?? $row['no_bc'] ?? $row['numero_bc'] ?? null);

        // Si aucune référence, ignorer la ligne
        if (empty($numEB) && empty($numDA) && empty($numBC)) {
            return null;
        }

        // Trouver la direction (obligatoire)
        $directionCode = trim($row['direction'] ?? '');
        $direction = $this->findDirection($directionCode);

        if (!$direction) {
            $this->errors[] = "Ligne {$this->rowCount}: Direction '{$directionCode}' non trouvee - ligne ignoree";
            return null; // Direction obligatoire, on ignore la ligne
        }

        // Trouver le service
        $serviceName = trim($row['service'] ?? '');
        $service = $this->findService($serviceName);

        // Trouver l'acheteur
        $acheteurName = trim($row['acheteur'] ?? '');
        $acheteur = $this->findAcheteur($acheteurName);

        // Trouver le fournisseur
        $fournisseurName = trim($row['fournisseur'] ?? '');
        $fournisseur = $this->findFournisseur($fournisseurName);

        // Extraire les données communes
        $demandeurNom = trim($row['demandeur'] ?? '');
        $estimation = $this->parseAmount($row['estimation'] ?? '0');
        $montant = $this->parseAmount($row['montant'] ?? '0');

        // Créer/mettre à jour l'EB si présent
        $ebId = null;
        if (!empty($numEB)) {
            $ebId = $this->processEB($row, $numEB, $direction, $service, $acheteur, $fournisseur, $demandeurNom, $estimation);
        }

        // Créer/mettre à jour la DA si présente
        $daId = null;
        if (!empty($numDA)) {
            $daId = $this->processDA($row, $numDA, $ebId, $direction, $service, $acheteur, $demandeurNom, $montant);
        }

        // Créer/mettre à jour le BC si présent
        if (!empty($numBC)) {
            $this->processBC($row, $numBC, $daId, $ebId, $direction, $acheteur, $fournisseur, $demandeurNom, $montant);
        }

        return null; // On gère les créations manuellement
    }

    protected function normalizeRowKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            // Convertir en minuscules, remplacer les espaces par des underscores
            $newKey = strtolower(trim($key));
            $newKey = str_replace([' ', '°', '/', '-'], ['_', '_', '_', '_'], $newKey);
            $newKey = preg_replace('/[^a-z0-9_]/', '', $newKey);
            $newKey = preg_replace('/_+/', '_', $newKey);
            $normalized[$newKey] = $value;
        }
        return $normalized;
    }

    protected function cleanReference(?string $ref): ?string
    {
        if (empty($ref)) return null;
        $ref = trim($ref);
        if ($ref === '0' || $ref === '') return null;
        return $ref;
    }

    protected function parseExcelDate($value): ?Carbon
    {
        if (empty($value) || $value === '0') return null;

        // Si c'est un nombre (date Excel serial)
        if (is_numeric($value)) {
            $serial = (int)$value;
            if ($serial > 0) {
                // Excel compte depuis le 1er janvier 1900, mais a un bug avec 1900 comme année bissextile
                // On doit donc soustraire 2 jours pour les dates >= 1er mars 1900
                $baseDate = Carbon::create(1899, 12, 30);
                return $baseDate->addDays($serial);
            }
        }

        // Essayer de parser comme date normale
        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function parseAmount($value): float
    {
        if (empty($value)) return 0;
        // Nettoyer le montant
        $clean = str_replace([' ', ',', 'FCFA', 'XAF'], ['', '.', '', ''], $value);
        return (float)$clean;
    }

    protected function findDirection(string $code): ?Direction
    {
        if (empty($code)) return null;
        $key = strtoupper(trim($code));
        return $this->directionsCache[$key] ?? null;
    }

    protected function findService(string $name): ?Service
    {
        if (empty($name)) return null;
        $key = strtoupper(trim($name));
        return $this->servicesCache[$key] ?? null;
    }

    protected function findAcheteur(string $name): ?User
    {
        if (empty($name)) return null;
        $key = strtoupper(trim($name));
        return $this->acheteursCache[$key] ?? null;
    }

    protected function findFournisseur(string $name): ?Fournisseur
    {
        if (empty($name)) return null;
        $key = strtoupper(trim($name));

        // 1. Recherche exacte
        if (isset($this->fournisseursCache[$key])) {
            return $this->fournisseursCache[$key];
        }

        // 2. Recherche partielle (le nom contient ou est contenu)
        foreach ($this->fournisseursCache as $cacheKey => $fournisseur) {
            if (str_contains($key, $cacheKey) || str_contains($cacheKey, $key)) {
                return $fournisseur;
            }
        }

        // 3. Recherche par mots-clés communs
        $keywords = [
            'TOTAL' => 'TOTAL-E',
            'ENERGIE' => 'TOTAL-E',
            'SPORAFRIC' => 'SPORAFRIC',
            'GTEC' => 'GTEC',
            'WECOM' => 'WECOM',
            'BUROTEC' => 'BUROTEC',
            'BUROTOP' => 'BUROTEC',
            'TRANSIT' => 'TRANSIT-EXP',
            'SATGURU' => 'SATGURU',
            'HARIOM' => 'HARIOM',
            'AERCO' => 'AERCO',
            'AEROPORT' => 'AERCO',
            'VISIONA' => 'VISIONA',
            'E2C' => 'E2C',
            'TRESOR' => 'TRESOR',
            'TRESO' => 'TRESOR',
            'TIMBRE' => 'EDT',
            'EDT' => 'EDT',
            'TRANS BONY' => 'TRANS-BONY',
            'TRANSBONY' => 'TRANS-BONY',
            'DIVERS' => 'DIVERS',
            'POLICE' => 'DIVERS',
        ];

        foreach ($keywords as $keyword => $targetCode) {
            if (str_contains($key, $keyword) && isset($this->fournisseursCache[$targetCode])) {
                return $this->fournisseursCache[$targetCode];
            }
        }

        // 4. Fournisseur par défaut "DIVERS" pour les non trouvés
        return $this->fournisseursCache['DIVERS'] ?? null;
    }

    protected function parseStatutEB(string $statut): StatutEB
    {
        $statut = strtoupper(trim($statut));

        if (str_contains($statut, 'TRAIT')) return StatutEB::TRAITE;
        if (str_contains($statut, 'ANNUL')) return StatutEB::ANNULE;
        // Vérifier CDG avant DG (CDG contient DG)
        if (str_contains($statut, 'CDG')) return StatutEB::EN_COURS_CDG;
        if (str_contains($statut, 'DFC')) return StatutEB::EN_COURS_DFC;
        if (str_contains($statut, ' DG') || preg_match('/\bDG\b/', $statut)) return StatutEB::EN_COURS_DG;
        if (str_contains($statut, 'ACH')) return StatutEB::EN_COURS_ACH;
        if (str_contains($statut, 'OK')) return StatutEB::EN_COURS_ACH;

        return StatutEB::EN_SUSPENS;
    }

    protected function parseStatutDA(string $statut): StatutDA
    {
        $statut = strtoupper(trim($statut));

        if (str_contains($statut, 'TRAIT')) return StatutDA::TRAITE;
        if (str_contains($statut, 'ANNUL')) return StatutDA::ANNULE;
        // Vérifier CDG avant DG (CDG contient DG)
        if (str_contains($statut, 'CDG')) return StatutDA::EN_COURS_CDG;
        if (str_contains($statut, 'DFC')) return StatutDA::EN_COURS_DFC;
        if (str_contains($statut, ' DG') || preg_match('/\bDG\b/', $statut)) return StatutDA::EN_COURS_DG;
        if (str_contains($statut, 'ACH')) return StatutDA::EN_COURS_ACH;
        if (str_contains($statut, 'OK')) return StatutDA::EN_COURS_ACH;

        return StatutDA::EN_COURS_ACH;
    }

    protected function parseStatutBC(string $statut): StatutBC
    {
        $statut = strtoupper(trim($statut));

        if (str_contains($statut, 'TRAIT') || str_contains($statut, 'TRESO')) return StatutBC::TRAITE;
        if (str_contains($statut, 'LIVR')) return StatutBC::LIVRE;
        if (str_contains($statut, 'ANNUL')) return StatutBC::ANNULE;
        if (str_contains($statut, 'CDG')) return StatutBC::EN_COURS_CDG;
        if (str_contains($statut, 'F/SSEUR') || str_contains($statut, 'FSSEUR')) return StatutBC::EN_COURS_FSSEUR;
        if (str_contains($statut, 'EN COURS')) return StatutBC::EN_COURS_A;
        if (str_contains($statut, 'N/C') || str_contains($statut, 'NC')) return StatutBC::NC;

        return StatutBC::NC;
    }

    protected function processEB(array $row, string $numEB, ?Direction $direction, ?Service $service, ?User $acheteur, ?Fournisseur $fournisseur, string $demandeurNom, float $estimation): ?string
    {
        // Chercher un EB existant par reference_origine
        $existingEB = ExpressionBesoin::where('reference_origine', $numEB)
            ->where('societe_id', $this->societeId)
            ->first();

        $dateEB = $this->parseExcelDate($row['date_eb'] ?? null);
        $objetEB = trim($row['description_du_besoin_et_quantite_souhaitee'] ?? $row['description_besoin'] ?? '');
        $statutEB = $this->parseStatutEB($row['statut_ebda'] ?? $row['statut_eb_da'] ?? '');

        $data = [
            'zone_id' => $direction?->zone_id ?? $this->defaultZoneId,
            'direction_id' => $direction?->id,
            'service_id' => $service?->id,
            'demandeur_nom' => $demandeurNom,
            'objet' => $objetEB ?: 'Objet non specifie',
            'estimation' => $estimation > 0 ? $estimation : 0,
            'acheteur_id' => $acheteur?->id,
            'fournisseur_suggere_id' => $fournisseur?->id,
            'statut' => $statutEB,
            'updated_by' => Auth::id(),
        ];

        if ($existingEB) {
            $existingEB->update($data);
            $this->ebUpdated++;
            return $existingEB->id;
        }

        // Créer nouvel EB
        $numero = $this->numerotation->genererNumero($this->societeId, 'EB');
        $eb = ExpressionBesoin::create(array_merge($data, [
            'societe_id' => $this->societeId,
            'numero' => $numero,
            'reference_origine' => $numEB,
            'date_expression' => $dateEB ?? now(),
            'demandeur_id' => Auth::id(),
            'created_by' => Auth::id(),
        ]));

        $this->ebCreated++;
        return $eb->id;
    }

    protected function processDA(array $row, string $numDA, ?string $ebId, ?Direction $direction, ?Service $service, ?User $acheteur, string $demandeurNom, float $montant): ?string
    {
        // Chercher une DA existante par reference_origine
        $existingDA = DemandeAchat::where('reference_origine', $numDA)
            ->where('societe_id', $this->societeId)
            ->first();

        $dateDA = $this->parseExcelDate($row['date_da'] ?? null);
        $objetDA = trim($row['description_da'] ?? '');
        $statutDA = $this->parseStatutDA($row['statut_ebda'] ?? $row['statut_eb_da'] ?? '');

        // Déterminer le type de demande (DA ou DAC)
        $typeDemande = TypeDemande::DA;
        if (str_contains(strtoupper($numDA), 'DAC')) {
            $typeDemande = TypeDemande::DAC;
        }

        $data = [
            'type_demande' => $typeDemande,
            'expression_besoin_id' => $ebId,
            'zone_id' => $direction?->zone_id ?? $this->defaultZoneId,
            'direction_id' => $direction?->id,
            'service_id' => $service?->id,
            'acheteur_id' => $acheteur?->id,
            'objet' => $objetDA ?: 'Objet non specifie',
            'montant' => $montant > 0 ? $montant : 0,
            'statut' => $statutDA,
            'updated_by' => Auth::id(),
        ];

        if ($existingDA) {
            $existingDA->update($data);
            $this->daUpdated++;
            return $existingDA->id;
        }

        // Créer nouvelle DA
        $typeCode = $typeDemande === TypeDemande::DAC ? 'DAC' : 'DA';
        $numero = $this->numerotation->genererNumero($this->societeId, $typeCode);
        $da = DemandeAchat::create(array_merge($data, [
            'societe_id' => $this->societeId,
            'numero' => $numero,
            'reference_origine' => $numDA,
            'date_demande' => $dateDA ?? now(),
            'demandeur_id' => Auth::id(),
            'created_by' => Auth::id(),
        ]));

        $this->daCreated++;
        return $da->id;
    }

    protected function processBC(array $row, string $numBC, ?string $daId, ?string $ebId, ?Direction $direction, ?User $acheteur, ?Fournisseur $fournisseur, string $demandeurNom, float $montant): ?string
    {
        // Fournisseur obligatoire pour un BC - utiliser DIVERS si non trouvé
        $fournisseurName = trim($row['fournisseur'] ?? '');
        if (!$fournisseur) {
            // Essayer de récupérer DIVERS comme fallback
            $fournisseur = Fournisseur::where('code', 'DIVERS')->first();
            if ($fournisseur) {
                $this->errors[] = "BC {$numBC}: Fournisseur '{$fournisseurName}' non trouve - utilisation de 'DIVERS'";
            } else {
                $this->errors[] = "BC {$numBC}: Fournisseur '{$fournisseurName}' non trouve et pas de fallback - BC ignore";
                return null;
            }
        }

        // Chercher un BC existant par reference_origine
        $existingBC = BonCommande::where('reference_origine', $numBC)
            ->where('societe_id', $this->societeId)
            ->first();

        $dateBC = $this->parseExcelDate($row['date_bc'] ?? null);
        $objetBC = trim($row['description_bc'] ?? '');
        $statutBC = $this->parseStatutBC($row['statut_bc'] ?? '');

        // Calculer TVA
        $montantHT = $montant;
        $tauxTVA = 19.25;
        $montantTVA = round($montantHT * ($tauxTVA / 100));
        $montantTTC = $montantHT + $montantTVA;

        $data = [
            'type_bc' => 'BCAL', // Par défaut
            'demande_achat_id' => $daId,
            'expression_besoin_id' => $ebId,
            'fournisseur_id' => $fournisseur?->id,
            'zone_id' => $direction?->zone_id ?? $this->defaultZoneId,
            'direction_id' => $direction?->id,
            'acheteur_id' => $acheteur?->id,
            'objet' => $objetBC ?: 'Objet non specifie',
            'montant_ht_xaf' => $montantHT > 0 ? $montantHT : 0,
            'taux_tva' => $tauxTVA,
            'montant_tva' => $montantTVA,
            'montant_ttc_xaf' => $montantTTC,
            'statut' => $statutBC,
            'updated_by' => Auth::id(),
        ];

        if ($existingBC) {
            $existingBC->update($data);
            $this->bcUpdated++;
            return $existingBC->id;
        }

        // Créer nouveau BC
        $numero = $this->numerotation->genererNumero($this->societeId, 'BC');
        $bc = BonCommande::create(array_merge($data, [
            'societe_id' => $this->societeId,
            'numero' => $numero,
            'reference_origine' => $numBC,
            'date_bc' => $dateBC ?? now(),
            'demandeur_id' => Auth::id(),
            'created_by' => Auth::id(),
        ]));

        $this->bcCreated++;
        return $bc->id;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getStats(): array
    {
        return [
            'eb_crees' => $this->ebCreated,
            'eb_mis_a_jour' => $this->ebUpdated,
            'da_crees' => $this->daCreated,
            'da_mis_a_jour' => $this->daUpdated,
            'bc_crees' => $this->bcCreated,
            'bc_mis_a_jour' => $this->bcUpdated,
        ];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
