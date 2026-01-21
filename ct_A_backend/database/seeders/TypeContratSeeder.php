<?php

namespace Database\Seeders;

use App\Models\TypeContrat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TypeContratSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'MISE_DISPO',
                'libelle' => 'Mise a disposition de personnel',
                'description' => 'Contrats avec des societes de mise a disposition de personnel (interim, prestation de services RH)',
            ],
            [
                'code' => 'LOCATION',
                'libelle' => 'Location',
                'description' => 'Contrats de location de materiel, vehicules, locaux',
            ],
            [
                'code' => 'MAINTENANCE',
                'libelle' => 'Maintenance',
                'description' => 'Contrats de maintenance preventive ou curative',
            ],
            [
                'code' => 'ABONNEMENT',
                'libelle' => 'Abonnement',
                'description' => 'Abonnements logiciels, telecoms, services recurrents',
            ],
            [
                'code' => 'ASSURANCE',
                'libelle' => 'Assurance',
                'description' => 'Contrats d\'assurance (vehicules, locaux, responsabilite civile)',
            ],
            [
                'code' => 'PRESTATION',
                'libelle' => 'Prestation de service',
                'description' => 'Prestations de services recurrentes (nettoyage, gardiennage, restauration)',
            ],
            [
                'code' => 'LICENCE',
                'libelle' => 'Licence logicielle',
                'description' => 'Licences logicielles avec renouvellement periodique',
            ],
            [
                'code' => 'SUPPORT',
                'libelle' => 'Support technique',
                'description' => 'Contrats de support et assistance technique',
            ],
        ];

        foreach ($types as $type) {
            TypeContrat::updateOrCreate(
                ['code' => $type['code']],
                [
                    'id' => Str::uuid(),
                    'libelle' => $type['libelle'],
                    'description' => $type['description'],
                    'actif' => true,
                ]
            );
        }
    }
}
