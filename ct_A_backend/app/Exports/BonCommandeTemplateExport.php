<?php

namespace App\Exports;

use App\Models\Zone;
use App\Models\Direction;
use App\Models\Fournisseur;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BonCommandeTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Donnees' => new BCDataSheet(),
            'Referentiels' => new BCReferentielsSheet(),
        ];
    }
}

class BCDataSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function headings(): array
    {
        return [
            'reference_origine',
            'type_bc* (BCAL/BCL/BCAI/BCI/IPO)',
            'fournisseur_code*',
            'zone_code',
            'direction_code*',
            'objet*',
            'nature_prestation',
            'montant_ht (FCFA)*',
            'taux_tva (%)',
            'conditions_paiement',
            'date_livraison_prevue (JJ/MM/AAAA)',
            'adresse_livraison',
            'numero_devis',
            'acheteur_matricule',
            'statut',
            'commentaire',
        ];
    }

    public function array(): array
    {
        return [
            [
                'BC-EXT-001',
                'BCAL',
                'FSSEUR001',
                'ZONE_EXEMPLE',
                'DIR_EXEMPLE',
                'Fournitures informatiques',
                'Livraison de materiel',
                '1500000',
                '19.25',
                'A reception',
                '28/02/2026',
                '123 Rue Exemple, Douala',
                'DEV-2026-001',
                'ACH001',
                'NC',
                'Commande urgente',
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
                    'startColor' => ['rgb' => 'DC2626'],
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
            'B' => 28,
            'C' => 18,
            'D' => 15,
            'E' => 18,
            'F' => 30,
            'G' => 25,
            'H' => 18,
            'I' => 12,
            'J' => 20,
            'K' => 28,
            'L' => 30,
            'M' => 18,
            'N' => 20,
            'O' => 15,
            'P' => 25,
        ];
    }
}

class BCReferentielsSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    public function headings(): array
    {
        return [
            'Type',
            'Code',
            'Libelle',
            'Description',
        ];
    }

    public function array(): array
    {
        $data = [];

        // Types de BC
        $data[] = ['TYPE_BC', 'BCAL', 'Bon de Commande Approvisionnement Local', 'Achats locaux standards'];
        $data[] = ['TYPE_BC', 'BCL', 'Bon de Commande Local', 'Commandes locales'];
        $data[] = ['TYPE_BC', 'BCAI', 'Bon de Commande Approvisionnement International', 'Imports standards'];
        $data[] = ['TYPE_BC', 'BCI', 'Bon de Commande International', 'Commandes internationales'];
        $data[] = ['TYPE_BC', 'IPO', 'International Purchase Order', 'Commandes internationales en anglais'];
        $data[] = ['', '', '', ''];

        // Conditions de paiement
        $data[] = ['CONDITION_PAIEMENT', 'A reception', 'Paiement a la reception', ''];
        $data[] = ['CONDITION_PAIEMENT', '30 jours', 'Paiement a 30 jours', ''];
        $data[] = ['CONDITION_PAIEMENT', '60 jours', 'Paiement a 60 jours', ''];
        $data[] = ['CONDITION_PAIEMENT', '50% Avance', '50% a la commande, 50% a la livraison', ''];
        $data[] = ['', '', '', ''];

        // Statuts BC
        $data[] = ['STATUT', 'NC', 'Non confirme (defaut)', ''];
        $data[] = ['STATUT', 'EN_COURS_A', 'En cours acheteur', ''];
        $data[] = ['STATUT', 'EN_COURS_CDG', 'En cours CDG', ''];
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
            $data[] = ['ZONE', $zone->code, $zone->libelle, ''];
        }

        // Directions
        $directions = Direction::where('actif', true)->with('zone')->get();
        foreach ($directions as $dir) {
            $data[] = ['DIRECTION', $dir->code, $dir->libelle_court, 'Zone: ' . ($dir->zone?->code ?? '-')];
        }

        $data[] = ['', '', '', ''];

        // Fournisseurs
        $fournisseurs = Fournisseur::where('statut', 'ACTIF')->limit(50)->get();
        foreach ($fournisseurs as $four) {
            $typeFournisseur = $four->type_fournisseur ? $four->type_fournisseur->value : '-';
            $data[] = ['FOURNISSEUR', $four->code ?? $four->id, $four->raison_sociale, $typeFournisseur];
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
            'A' => 22,
            'B' => 25,
            'C' => 45,
            'D' => 40,
        ];
    }
}
