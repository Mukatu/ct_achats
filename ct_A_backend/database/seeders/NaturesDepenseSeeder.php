<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NaturesDepenseSeeder extends Seeder
{
    public function run(): void
    {
        $societe = DB::table('societes')->where('code', 'CT')->first();
        $societeId = $societe->id;

        // Natures de dépense basées sur le plan comptable OHADA (classe 6)
        $natures = [
            // Niveau 1 - Catégories principales
            ['code' => '60', 'libelle' => 'Achats', 'type' => 'FONCTIONNEMENT', 'compte' => '60', 'niveau' => 1, 'imputable' => false],
            ['code' => '61', 'libelle' => 'Transports', 'type' => 'FONCTIONNEMENT', 'compte' => '61', 'niveau' => 1, 'imputable' => false],
            ['code' => '62', 'libelle' => 'Services extérieurs A', 'type' => 'FONCTIONNEMENT', 'compte' => '62', 'niveau' => 1, 'imputable' => false],
            ['code' => '63', 'libelle' => 'Services extérieurs B', 'type' => 'FONCTIONNEMENT', 'compte' => '63', 'niveau' => 1, 'imputable' => false],
            ['code' => '64', 'libelle' => 'Impôts et taxes', 'type' => 'FONCTIONNEMENT', 'compte' => '64', 'niveau' => 1, 'imputable' => false],
            ['code' => '65', 'libelle' => 'Autres charges', 'type' => 'FONCTIONNEMENT', 'compte' => '65', 'niveau' => 1, 'imputable' => false],
            
            // Investissements (classe 2)
            ['code' => '21', 'libelle' => 'Immobilisations incorporelles', 'type' => 'INVESTISSEMENT', 'compte' => '21', 'niveau' => 1, 'imputable' => false],
            ['code' => '22', 'libelle' => 'Terrains', 'type' => 'INVESTISSEMENT', 'compte' => '22', 'niveau' => 1, 'imputable' => false],
            ['code' => '23', 'libelle' => 'Bâtiments', 'type' => 'INVESTISSEMENT', 'compte' => '23', 'niveau' => 1, 'imputable' => false],
            ['code' => '24', 'libelle' => 'Matériel', 'type' => 'INVESTISSEMENT', 'compte' => '24', 'niveau' => 1, 'imputable' => false],
        ];

        $parentIds = [];
        
        foreach ($natures as $nature) {
            $id = Str::uuid()->toString();
            $parentIds[$nature['code']] = $id;
            
            DB::table('natures_depense')->insert([
                'id' => $id,
                'societe_id' => $societeId,
                'code' => $nature['code'],
                'libelle' => $nature['libelle'],
                'type' => $nature['type'],
                'compte_ohada' => $nature['compte'],
                'niveau' => $nature['niveau'],
                'imputable' => $nature['imputable'],
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Niveau 2 - Sous-catégories
        $sousNatures = [
            // Achats (60)
            ['code' => '601', 'libelle' => 'Achats de marchandises', 'parent' => '60', 'compte' => '601'],
            ['code' => '602', 'libelle' => 'Achats de matières premières', 'parent' => '60', 'compte' => '602'],
            ['code' => '604', 'libelle' => 'Achats stockés', 'parent' => '60', 'compte' => '604'],
            ['code' => '605', 'libelle' => 'Autres achats', 'parent' => '60', 'compte' => '605'],
            ['code' => '608', 'libelle' => 'Achats d\'emballages', 'parent' => '60', 'compte' => '608'],
            
            // Transports (61)
            ['code' => '611', 'libelle' => 'Transports sur achats', 'parent' => '61', 'compte' => '611'],
            ['code' => '612', 'libelle' => 'Transports sur ventes', 'parent' => '61', 'compte' => '612'],
            ['code' => '613', 'libelle' => 'Transports pour le compte de tiers', 'parent' => '61', 'compte' => '613'],
            ['code' => '618', 'libelle' => 'Autres frais de transport', 'parent' => '61', 'compte' => '618'],
            
            // Services extérieurs A (62)
            ['code' => '621', 'libelle' => 'Sous-traitance générale', 'parent' => '62', 'compte' => '621'],
            ['code' => '622', 'libelle' => 'Locations et charges locatives', 'parent' => '62', 'compte' => '622'],
            ['code' => '623', 'libelle' => 'Redevances de crédit-bail', 'parent' => '62', 'compte' => '623'],
            ['code' => '624', 'libelle' => 'Entretien, réparations et maintenance', 'parent' => '62', 'compte' => '624'],
            ['code' => '625', 'libelle' => 'Primes d\'assurance', 'parent' => '62', 'compte' => '625'],
            ['code' => '626', 'libelle' => 'Études, recherches et documentation', 'parent' => '62', 'compte' => '626'],
            ['code' => '627', 'libelle' => 'Publicité, publications, relations publiques', 'parent' => '62', 'compte' => '627'],
            ['code' => '628', 'libelle' => 'Frais de télécommunication', 'parent' => '62', 'compte' => '628'],
            
            // Services extérieurs B (63)
            ['code' => '631', 'libelle' => 'Frais bancaires', 'parent' => '63', 'compte' => '631'],
            ['code' => '632', 'libelle' => 'Rémunérations d\'intermédiaires', 'parent' => '63', 'compte' => '632'],
            ['code' => '633', 'libelle' => 'Frais de formation du personnel', 'parent' => '63', 'compte' => '633'],
            ['code' => '634', 'libelle' => 'Redevances pour brevets et licences', 'parent' => '63', 'compte' => '634'],
            ['code' => '635', 'libelle' => 'Cotisations', 'parent' => '63', 'compte' => '635'],
            ['code' => '637', 'libelle' => 'Rémunérations du personnel extérieur', 'parent' => '63', 'compte' => '637'],
            ['code' => '638', 'libelle' => 'Autres charges externes', 'parent' => '63', 'compte' => '638'],
            
            // Impôts et taxes (64)
            ['code' => '641', 'libelle' => 'Impôts et taxes directs', 'parent' => '64', 'compte' => '641'],
            ['code' => '645', 'libelle' => 'Impôts et taxes indirects', 'parent' => '64', 'compte' => '645'],
            ['code' => '646', 'libelle' => 'Droits d\'enregistrement', 'parent' => '64', 'compte' => '646'],
            ['code' => '648', 'libelle' => 'Autres impôts et taxes', 'parent' => '64', 'compte' => '648'],
            
            // Autres charges (65)
            ['code' => '651', 'libelle' => 'Pertes sur créances', 'parent' => '65', 'compte' => '651'],
            ['code' => '658', 'libelle' => 'Charges diverses', 'parent' => '65', 'compte' => '658'],
            
            // Immobilisations (21-24)
            ['code' => '211', 'libelle' => 'Frais de recherche et développement', 'parent' => '21', 'compte' => '211', 'type' => 'INVESTISSEMENT'],
            ['code' => '212', 'libelle' => 'Brevets, licences, logiciels', 'parent' => '21', 'compte' => '212', 'type' => 'INVESTISSEMENT'],
            ['code' => '241', 'libelle' => 'Matériel industriel', 'parent' => '24', 'compte' => '241', 'type' => 'INVESTISSEMENT'],
            ['code' => '244', 'libelle' => 'Matériel de transport', 'parent' => '24', 'compte' => '244', 'type' => 'INVESTISSEMENT'],
            ['code' => '245', 'libelle' => 'Matériel de bureau', 'parent' => '24', 'compte' => '245', 'type' => 'INVESTISSEMENT'],
            ['code' => '246', 'libelle' => 'Matériel informatique', 'parent' => '24', 'compte' => '246', 'type' => 'INVESTISSEMENT'],
            ['code' => '248', 'libelle' => 'Autres matériels', 'parent' => '24', 'compte' => '248', 'type' => 'INVESTISSEMENT'],
        ];

        foreach ($sousNatures as $sn) {
            $type = $sn['type'] ?? 'FONCTIONNEMENT';
            
            DB::table('natures_depense')->insert([
                'id' => Str::uuid(),
                'societe_id' => $societeId,
                'code' => $sn['code'],
                'libelle' => $sn['libelle'],
                'type' => $type,
                'compte_ohada' => $sn['compte'],
                'parent_id' => $parentIds[$sn['parent']] ?? null,
                'niveau' => 2,
                'imputable' => true,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
