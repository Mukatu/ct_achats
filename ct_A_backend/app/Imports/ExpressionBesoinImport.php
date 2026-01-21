<?php

namespace App\Imports;

use App\Models\ExpressionBesoin;
use App\Models\Zone;
use App\Models\Direction;
use App\Models\Service;
use App\Models\User;
use App\Models\Fournisseur;
use App\Services\NumerotationService;
use App\Enums\StatutEB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ExpressionBesoinImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
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

        // Trouver les références
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
        $service = !empty($row['service_code'])
            ? Service::where('code', trim($row['service_code']))->first()
            : null;

        if (!$direction) {
            $this->errors[] = "Ligne {$this->rowCount}: Direction non trouvée";
            return null;
        }

        // Trouver l'acheteur si spécifié
        $acheteurId = null;
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

        // Trouver le fournisseur suggéré si spécifié
        $fournisseurId = null;
        if (!empty($row['fournisseur_code'])) {
            $fournisseur = Fournisseur::where('code', trim($row['fournisseur_code']))
                ->orWhere('id', trim($row['fournisseur_code']))
                ->first();
            if ($fournisseur) {
                $fournisseurId = $fournisseur->id;
            } else {
                $this->errors[] = "Ligne {$this->rowCount}: Fournisseur '{$row['fournisseur_code']}' non trouvé";
            }
        }

        // Déterminer le statut
        $statut = StatutEB::EN_SUSPENS;
        if (!empty($row['statut'])) {
            $statutValides = ['EN_SUSPENS', 'EN_COURS_ACH'];
            $statutInput = strtoupper(trim($row['statut']));
            if (in_array($statutInput, $statutValides)) {
                $statut = StatutEB::from($statutInput);
            }
        }

        // Parser la date
        $dateBesoin = null;
        if (!empty($row['date_besoin_jjmmaaaa'])) {
            try {
                $dateBesoin = Carbon::createFromFormat('d/m/Y', trim($row['date_besoin_jjmmaaaa']));
            } catch (\Exception $e) {
                // Ignorer si format incorrect
            }
        }

        // Générer le numéro
        $numero = $this->numerotation->genererNumero($this->societeId, 'EB');

        return new ExpressionBesoin([
            'societe_id' => $this->societeId,
            'numero' => $numero,
            'date_expression' => now(),
            'zone_id' => $zoneId,
            'direction_id' => $direction->id,
            'service_id' => $service?->id,
            'demandeur_id' => Auth::id(),
            'demandeur_nom' => trim($row['demandeur_nom']),
            'objet' => trim($row['objet']),
            'description_detaillee' => $row['description_detaillee'] ?? null,
            'quantite_souhaitee' => !empty($row['quantite_souhaitee']) ? (int)$row['quantite_souhaitee'] : null,
            'date_besoin' => $dateBesoin,
            'estimation' => (float)str_replace([' ', ','], ['', '.'], $row['estimation_fcfa']),
            'acheteur_id' => $acheteurId,
            'fournisseur_suggere_id' => $fournisseurId,
            'statut' => $statut,
            'commentaire' => $row['commentaire'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'zone_code' => 'nullable|string',
            'direction_code' => 'required|string',
            'demandeur_nom' => 'required|string|max:255',
            'objet' => 'required|string|max:500',
            'estimation_fcfa' => 'required|numeric|min:0',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'direction_code.required' => 'Le code direction est obligatoire',
            'demandeur_nom.required' => 'Le nom du demandeur est obligatoire',
            'objet.required' => 'L\'objet est obligatoire',
            'estimation_fcfa.required' => 'L\'estimation est obligatoire',
            'estimation_fcfa.numeric' => 'L\'estimation doit etre un nombre',
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
