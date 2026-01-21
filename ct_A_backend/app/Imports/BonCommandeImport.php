<?php

namespace App\Imports;

use App\Models\BonCommande;
use App\Models\Zone;
use App\Models\Direction;
use App\Models\Fournisseur;
use App\Models\User;
use App\Services\NumerotationService;
use App\Enums\StatutBC;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BonCommandeImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
{
    use SkipsFailures;

    protected NumerotationService $numerotation;
    protected string $societeId;
    protected ?string $defaultZoneId;
    protected int $rowCount = 0;
    protected array $errors = [];

    public function __construct(NumerotationService $numerotation)
    {
        $this->numerotation = $numerotation;
        $user = Auth::user();
        $this->societeId = $user->service->direction->zone->societe_id;
        $this->defaultZoneId = $user->service->direction->zone_id;
    }

    public function model(array $row)
    {
        $this->rowCount++;

        // Normaliser le type BC
        $typeBC = strtoupper(trim($row['type_bc_bcalbclbcaibciipo']));
        $typesValides = ['BCAL', 'BCL', 'BCAI', 'BCI', 'IPO'];
        if (!in_array($typeBC, $typesValides)) {
            $this->errors[] = "Ligne {$this->rowCount}: Type BC invalide (doit etre BCAL, BCL, BCAI, BCI ou IPO)";
            return null;
        }

        // Trouver les références
        $fournisseur = Fournisseur::where('code', trim($row['fournisseur_code']))
            ->orWhere('id', trim($row['fournisseur_code']))
            ->first();

        if (!$fournisseur) {
            $this->errors[] = "Ligne {$this->rowCount}: Fournisseur non trouve";
            return null;
        }

        $zone = null;
        if (!empty($row['zone_code'])) {
            $zone = Zone::where('code', trim($row['zone_code']))->first();
            if (!$zone) {
                $this->errors[] = "Ligne {$this->rowCount}: Zone '{$row['zone_code']}' non trouvée";
            }
        }
        // Si pas de zone spécifiée, utiliser la zone par défaut de l'utilisateur
        $zoneId = $zone ? $zone->id : $this->defaultZoneId;

        $direction = Direction::where('code', trim($row['direction_code']))->first();

        if (!$direction) {
            $this->errors[] = "Ligne {$this->rowCount}: Direction non trouvée";
            return null;
        }

        // Parser les valeurs numériques
        $montantHT = (float)str_replace([' ', ','], ['', '.'], $row['montant_ht_fcfa']);
        $tauxTVA = !empty($row['taux_tva'])
            ? (float)str_replace([' ', ','], ['', '.'], $row['taux_tva'])
            : 19.25;

        // Parser la date de livraison
        $dateLivraison = null;
        if (!empty($row['date_livraison_prevue_jjmmaaaa'])) {
            try {
                $dateLivraison = Carbon::createFromFormat('d/m/Y', trim($row['date_livraison_prevue_jjmmaaaa']));
            } catch (\Exception $e) {
                // Ignorer si format incorrect
            }
        }

        // Trouver l'acheteur si spécifié
        $acheteurId = Auth::id(); // Par défaut, l'utilisateur connecté
        if (!empty($row['acheteur_matricule'])) {
            $acheteur = User::where('matricule', trim($row['acheteur_matricule']))
                ->where('est_acheteur', true)
                ->first();
            if ($acheteur) {
                $acheteurId = $acheteur->id;
            } else {
                $this->errors[] = "Ligne {$this->rowCount}: Acheteur '{$row['acheteur_matricule']}' non trouvé";
            }
        }

        // Déterminer le statut
        $statut = StatutBC::NC;
        if (!empty($row['statut'])) {
            $statutValides = ['NC', 'EN_COURS_A', 'EN_COURS_CDG'];
            $statutInput = strtoupper(trim($row['statut']));
            if (in_array($statutInput, $statutValides)) {
                $statut = StatutBC::from($statutInput);
            }
        }

        // Générer le numéro
        $numero = $this->numerotation->genererNumero($this->societeId, 'BC');

        // Calculer les montants
        $montantTVA = round($montantHT * ($tauxTVA / 100));
        $montantTTC = $montantHT + $montantTVA;

        return new BonCommande([
            'societe_id' => $this->societeId,
            'numero' => $numero,
            'type_bc' => $typeBC,
            'date_bc' => now(),
            'fournisseur_id' => $fournisseur->id,
            'zone_id' => $zoneId,
            'direction_id' => $direction->id,
            'demandeur_id' => Auth::id(),
            'acheteur_id' => $acheteurId,
            'objet' => trim($row['objet']),
            'nature_prestation' => $row['nature_prestation'] ?? null,
            'montant_ht_xaf' => $montantHT,
            'taux_tva' => $tauxTVA,
            'montant_tva' => $montantTVA,
            'montant_ttc_xaf' => $montantTTC,
            'conditions_paiement' => $row['conditions_paiement'] ?? 'A reception',
            'date_livraison_prevue' => $dateLivraison,
            'adresse_livraison' => $row['adresse_livraison'] ?? null,
            'numero_devis' => $row['numero_devis'] ?? null,
            'statut' => $statut,
            'commentaire' => $row['commentaire'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'type_bc_bcalbclbcaibciipo' => 'required|string',
            'fournisseur_code' => 'required|string',
            'zone_code' => 'nullable|string',
            'direction_code' => 'required|string',
            'objet' => 'required|string|max:500',
            'montant_ht_fcfa' => 'required|numeric|min:0',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'type_bc_bcalbclbcaibciipo.required' => 'Le type BC est obligatoire',
            'fournisseur_code.required' => 'Le code fournisseur est obligatoire',
            'direction_code.required' => 'Le code direction est obligatoire',
            'objet.required' => 'L\'objet est obligatoire',
            'montant_ht_fcfa.required' => 'Le montant HT est obligatoire',
            'montant_ht_fcfa.numeric' => 'Le montant HT doit etre un nombre',
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
