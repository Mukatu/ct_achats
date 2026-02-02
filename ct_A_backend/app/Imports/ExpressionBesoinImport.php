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
    protected int $createdCount = 0;
    protected int $updatedCount = 0;
    protected int $skippedCount = 0;
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

        // Vérifier si une référence d'origine est fournie
        $referenceOrigine = !empty($row['reference_origine']) ? trim($row['reference_origine']) : null;

        // Chercher un enregistrement existant avec cette référence
        $existingEB = null;
        if ($referenceOrigine) {
            $existingEB = ExpressionBesoin::where('reference_origine', $referenceOrigine)
                ->where('societe_id', $this->societeId)
                ->first();
        }

        // Trouver les références
        $zone = null;
        if (!empty($row['zone_code'])) {
            $zone = Zone::where('code', trim($row['zone_code']))->first();
            if (!$zone) {
                $this->errors[] = "Ligne {$this->rowCount}: Zone '{$row['zone_code']}' non trouvée";
            }
        }
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
            $statutValides = ['EN_SUSPENS', 'EN_COURS_ACH', 'EN_COURS_CDG', 'EN_COURS_DFC', 'EN_COURS_DG', 'TRAITE', 'ANNULE'];
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

        // Données à insérer/mettre à jour
        $data = [
            'zone_id' => $zoneId,
            'direction_id' => $direction->id,
            'service_id' => $service?->id,
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
            'updated_by' => Auth::id(),
        ];

        // Si enregistrement existant, mettre à jour
        if ($existingEB) {
            $existingEB->update($data);
            $this->updatedCount++;
            return null; // Ne pas créer de nouvel enregistrement
        }

        // Sinon, créer un nouvel enregistrement
        $this->createdCount++;
        $numero = $this->numerotation->genererNumero($this->societeId, 'EB');

        return new ExpressionBesoin(array_merge($data, [
            'societe_id' => $this->societeId,
            'numero' => $numero,
            'reference_origine' => $referenceOrigine,
            'date_expression' => now(),
            'demandeur_id' => Auth::id(),
            'created_by' => Auth::id(),
        ]));
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

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
