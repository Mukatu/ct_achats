<?php

namespace App\Exports;

use App\Models\Zone;
use App\Models\Direction;
use App\Models\Service;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DemandeAchatTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Donnees' => new DADataSheet(),
            'Referentiels' => new DAReferentielsSheet(),
        ];
    }
}

class DADataSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function headings(): array
    {
        return [
            'reference_origine',
            'type_demande* (DA ou DAC)',
            'zone_code',
            'direction_code*',
            'service_code',
            'objet*',
            'description',
            'montant (FCFA)*',
            'acheteur_matricule',
            'statut',
            'commentaire',
        ];
    }

    public function array(): array
    {
        return [
            [
                'DA-EXT-001',
                'DA',
                'ZONE_EXEMPLE',
                'DIR_EXEMPLE',
                'SERV_EXEMPLE',
                'Achat materiel informatique',
                'Acquisition de 5 ordinateurs portables',
                '2500000',
                'ACH001',
                'EN_COURS_ACH',
                'Urgent',
            ],
            [
                'DAC-EXT-001',
                'DAC',
                'ZONE_EXEMPLE',
                'DIR_EXEMPLE',
                '',
                'Fournitures de bureau',
                'Achat urgent de consommables',
                '75000',
                '',
                'EN_COURS_ACH',
                '',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '7C3AED'],
                ],
            ],
            2 => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FEF3C7'],
                ],
                'font' => ['italic' => true, 'color' => ['rgb' => '92400E']],
            ],
            3 => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D1FAE5'],
                ],
                'font' => ['italic' => true, 'color' => ['rgb' => '065F46']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,  // reference_origine
            'B' => 22,
            'C' => 15,
            'D' => 18,
            'E' => 15,
            'F' => 35,
            'G' => 40,
            'H' => 18,
            'I' => 20,
            'J' => 15,
            'K' => 25,
        ];
    }
}

class DAReferentielsSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function headings(): array
    {
        return [
            'Type',
            'Code',
            'Libelle',
            'Parent',
        ];
    }

    public function array(): array
    {
        $data = [];

        // Types de demande
        $data[] = ['TYPE_DEMANDE', 'DA', 'Demande d\'Achat classique', '-'];
        $data[] = ['TYPE_DEMANDE', 'DAC', 'Demande d\'Achat Caisse (depenses directes)', '-'];
        $data[] = ['', '', '', ''];

        // Statuts DA
        $data[] = ['STATUT', 'EN_COURS_ACH', 'En cours acheteur (defaut)', '-'];
        $data[] = ['STATUT', 'EN_COURS_CDG', 'En cours CDG', '-'];
        $data[] = ['', '', '', ''];

        // Acheteurs
        $acheteurs = User::where('est_acheteur', true)->where('actif', true)->get();
        foreach ($acheteurs as $ach) {
            $data[] = ['ACHETEUR', $ach->matricule, $ach->prenom . ' ' . $ach->nom, $ach->email];
        }
        $data[] = ['', '', '', ''];

        // Zones
        $zones = Zone::where('actif', true)->get();
        foreach ($zones as $zone) {
            $data[] = ['ZONE', $zone->code, $zone->libelle, '-'];
        }

        // Directions
        $directions = Direction::where('actif', true)->with('zone')->get();
        foreach ($directions as $dir) {
            $data[] = ['DIRECTION', $dir->code, $dir->libelle_court, $dir->zone?->code ?? '-'];
        }

        // Services
        $services = Service::where('actif', true)->with('direction')->get();
        foreach ($services as $svc) {
            $data[] = ['SERVICE', $svc->code, $svc->libelle, $svc->direction?->code ?? '-'];
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '059669'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 20,
            'C' => 45,
            'D' => 20,
        ];
    }
}
