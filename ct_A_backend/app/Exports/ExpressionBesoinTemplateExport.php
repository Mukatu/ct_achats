<?php

namespace App\Exports;

use App\Models\Zone;
use App\Models\Direction;
use App\Models\Service;
use App\Models\User;
use App\Models\Fournisseur;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ExpressionBesoinTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Donnees' => new EBDataSheet(),
            'Referentiels' => new EBReferentielsSheet(),
        ];
    }
}

class EBDataSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function headings(): array
    {
        return [
            'reference_origine',
            'zone_code',
            'direction_code*',
            'service_code',
            'demandeur_nom*',
            'objet*',
            'description_detaillee',
            'quantite_souhaitee',
            'date_besoin (JJ/MM/AAAA)',
            'estimation (FCFA)*',
            'acheteur_matricule',
            'fournisseur_code',
            'statut',
            'commentaire',
        ];
    }

    public function array(): array
    {
        // Exemple de données
        return [
            [
                'EB-EXT-001',
                'ZONE_EXEMPLE',
                'DIR_EXEMPLE',
                'SERV_EXEMPLE',
                'Jean DUPONT',
                'Fournitures de bureau',
                'Achat de ramettes de papier A4',
                '100',
                '15/02/2026',
                '250000',
                'ACH001',
                'FSSEUR001',
                'EN_SUSPENS',
                'Besoin urgent',
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
                    'startColor' => ['rgb' => '1E40AF'],
                ],
            ],
            2 => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FEF3C7'],
                ],
                'font' => ['italic' => true, 'color' => ['rgb' => '92400E']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,  // reference_origine
            'B' => 15,
            'C' => 18,
            'D' => 15,
            'E' => 20,
            'F' => 30,
            'G' => 35,
            'H' => 18,
            'I' => 22,
            'J' => 18,
            'K' => 20,
            'L' => 18,
            'M' => 15,
            'N' => 25,
        ];
    }
}

class EBReferentielsSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths
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

        // Statuts EB
        $data[] = ['STATUT', 'EN_SUSPENS', 'En suspens (defaut)', '-'];
        $data[] = ['STATUT', 'EN_COURS_ACH', 'En cours acheteur', '-'];
        $data[] = ['', '', '', ''];

        // Acheteurs
        $acheteurs = User::where('est_acheteur', true)->where('actif', true)->get();
        foreach ($acheteurs as $ach) {
            $data[] = ['ACHETEUR', $ach->matricule, $ach->prenom . ' ' . $ach->nom, $ach->email];
        }
        $data[] = ['', '', '', ''];

        // Fournisseurs
        $fournisseurs = Fournisseur::where('statut', 'ACTIF')->limit(50)->get();
        foreach ($fournisseurs as $four) {
            $typeFournisseur = $four->type_fournisseur ? $four->type_fournisseur->value : '-';
            $data[] = ['FOURNISSEUR', $four->code ?? $four->id, $four->raison_sociale, $typeFournisseur];
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
            'A' => 15,
            'B' => 20,
            'C' => 40,
            'D' => 20,
        ];
    }
}
