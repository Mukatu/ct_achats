<?php

namespace App\Imports;

use App\Models\DemandeAchat;
use App\Models\Zone;
use App\Models\Direction;
use App\Models\Service;
use App\Models\User;
use App\Services\NumerotationService;
use App\Enums\StatutDA;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Illuminate\Support\Facades\Auth;

class DemandeAchatImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
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

        // Normaliser le type de demande
        $typeDemande = strtoupper(trim($row['type_demande_da_ou_dac']));
        if (!in_array($typeDemande, ['DA', 'DAC'])) {
            $this->errors[] = "Ligne {$this->rowCount}: Type de demande invalide (doit etre DA ou DAC)";
            return null;
        }

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

        // Déterminer le statut
        $statut = StatutDA::EN_COURS_ACH;
        if (!empty($row['statut'])) {
            $statutValides = ['EN_COURS_ACH', 'EN_COURS_CDG'];
            $statutInput = strtoupper(trim($row['statut']));
            if (in_array($statutInput, $statutValides)) {
                $statut = StatutDA::from($statutInput);
            }
        }

        // Générer le numéro selon le type
        $numero = $this->numerotation->genererNumero($this->societeId, $typeDemande);

        return new DemandeAchat([
            'societe_id' => $this->societeId,
            'numero' => $numero,
            'type_demande' => $typeDemande,
            'date_demande' => now(),
            'zone_id' => $zoneId,
            'direction_id' => $direction->id,
            'service_id' => $service?->id,
            'demandeur_id' => Auth::id(),
            'acheteur_id' => $acheteurId,
            'objet' => trim($row['objet']),
            'description' => $row['description'] ?? null,
            'montant' => (float)str_replace([' ', ','], ['', '.'], $row['montant_fcfa']),
            'statut' => $statut,
            'commentaire' => $row['commentaire'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'type_demande_da_ou_dac' => 'required|string|in:DA,DAC,da,dac',
            'zone_code' => 'nullable|string',
            'direction_code' => 'required|string',
            'objet' => 'required|string|max:500',
            'montant_fcfa' => 'required|numeric|min:0',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'type_demande_da_ou_dac.required' => 'Le type de demande est obligatoire',
            'type_demande_da_ou_dac.in' => 'Le type de demande doit etre DA ou DAC',
            'direction_code.required' => 'Le code direction est obligatoire',
            'objet.required' => 'L\'objet est obligatoire',
            'montant_fcfa.required' => 'Le montant est obligatoire',
            'montant_fcfa.numeric' => 'Le montant doit etre un nombre',
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
