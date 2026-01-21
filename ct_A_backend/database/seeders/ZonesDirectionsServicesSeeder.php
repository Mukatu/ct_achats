<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ZonesDirectionsServicesSeeder extends Seeder
{
    public function run(): void
    {
        $societe = DB::table('societes')->where('code', 'CT')->first();
        $societeId = $societe->id;

        // ============================================================
        // ZONES (3 zones : DRC/Brazzaville, DRA/Pointe-Noire, DRE/Dolisie)
        // ============================================================
        $zones = [
            ['code' => 'DRC', 'libelle' => 'Direction Régionale Centre (Brazzaville)', 'ville' => 'Brazzaville', 'ordre' => 1],
            ['code' => 'DRA', 'libelle' => 'Direction Régionale A (Pointe-Noire)', 'ville' => 'Pointe-Noire', 'ordre' => 2],
            ['code' => 'DRE', 'libelle' => 'Direction Régionale E (Dolisie)', 'ville' => 'Dolisie', 'ordre' => 3],
        ];

        $zoneIds = [];
        foreach ($zones as $zone) {
            $id = Str::uuid()->toString();
            $zoneIds[$zone['code']] = $id;
            
            DB::table('zones')->insert([
                'id' => $id,
                'societe_id' => $societeId,
                'code' => $zone['code'],
                'libelle' => $zone['libelle'],
                'ville' => $zone['ville'],
                'ordre_affichage' => $zone['ordre'],
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ============================================================
        // DIRECTIONS (rattachées à DRC par défaut)
        // ============================================================
        $directions = [
            ['code' => 'DG', 'libelle' => 'Direction Générale', 'libelle_court' => 'DG', 'ordre' => 1],
            ['code' => 'DARH', 'libelle' => 'Direction Administrative et Ressources Humaines', 'libelle_court' => 'DARH', 'ordre' => 2],
            ['code' => 'DFC', 'libelle' => 'Direction Financière et Comptable', 'libelle_court' => 'DFC', 'ordre' => 3],
            ['code' => 'DIP', 'libelle' => 'Direction Infrastructure et Projets', 'libelle_court' => 'DIP', 'ordre' => 4],
            ['code' => 'DMTD', 'libelle' => 'Direction Marketing et Transformation Digitale', 'libelle_court' => 'DMTD', 'ordre' => 5],
            ['code' => 'DEW', 'libelle' => 'Direction Enterprise & Wholesale', 'libelle_court' => 'DEW', 'ordre' => 6],
            ['code' => 'DJR', 'libelle' => 'Direction Juridique et Réglementation', 'libelle_court' => 'DJR', 'ordre' => 7],
            ['code' => 'DOM', 'libelle' => 'Direction des Opérations et Maintenance', 'libelle_court' => 'DOM', 'ordre' => 8],
            ['code' => 'DT', 'libelle' => 'Direction Technique', 'libelle_court' => 'DT', 'ordre' => 9],
            ['code' => 'QHSE', 'libelle' => 'Qualité Hygiène Sécurité Environnement', 'libelle_court' => 'QHSE', 'ordre' => 10],
            ['code' => 'DEC', 'libelle' => 'Direction Expérience Client', 'libelle_court' => 'DEC', 'ordre' => 11],
            ['code' => 'DVD', 'libelle' => 'Direction Ventes et Distribution', 'libelle_court' => 'DVD', 'ordre' => 12],
            ['code' => 'DCM', 'libelle' => 'Direction Commerciale', 'libelle_court' => 'DCM', 'ordre' => 13],
        ];

        $directionIds = [];
        foreach ($directions as $dir) {
            $id = Str::uuid()->toString();
            $directionIds[$dir['code']] = $id;
            
            DB::table('directions')->insert([
                'id' => $id,
                'zone_id' => $zoneIds['DRC'], // Toutes rattachées à Brazzaville par défaut
                'code' => $dir['code'],
                'libelle' => $dir['libelle'],
                'libelle_court' => $dir['libelle_court'],
                'ordre_affichage' => $dir['ordre'],
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ============================================================
        // SERVICES (rattachés aux directions)
        // ============================================================
        $services = [
            // DG
            ['code' => 'CAB', 'libelle' => 'Cabinet', 'direction' => 'DG'],
            
            // DARH
            ['code' => 'APP', 'libelle' => 'Approvisionnements', 'direction' => 'DARH'],
            ['code' => 'ASSST', 'libelle' => 'Assistanat', 'direction' => 'DARH'],
            ['code' => 'PERS', 'libelle' => 'Personnel', 'direction' => 'DARH'],
            ['code' => 'SEC_DARH', 'libelle' => 'Secrétariat DARH', 'direction' => 'DARH'],
            
            // DFC
            ['code' => 'LOG', 'libelle' => 'Logistique', 'direction' => 'DFC'],
            ['code' => 'TRESO', 'libelle' => 'Trésorerie', 'direction' => 'DFC'],
            ['code' => 'CDG', 'libelle' => 'Contrôle de Gestion', 'direction' => 'DFC'],
            ['code' => 'ACH', 'libelle' => 'Achats', 'direction' => 'DFC'],
            ['code' => 'COMPTA', 'libelle' => 'Comptabilité', 'direction' => 'DFC'],
            ['code' => 'SEC_DFC', 'libelle' => 'Secrétariat DFC', 'direction' => 'DFC'],
            
            // DIP
            ['code' => 'PROJ', 'libelle' => 'Projets', 'direction' => 'DIP'],
            ['code' => 'RESEAU', 'libelle' => 'Réseau Fixe', 'direction' => 'DIP'],
            ['code' => 'EXT', 'libelle' => 'Extension', 'direction' => 'DIP'],
            ['code' => 'INFRA_IP', 'libelle' => 'Infrastructure IP', 'direction' => 'DIP'],
            
            // DMTD
            ['code' => 'MKTG', 'libelle' => 'Marketing', 'direction' => 'DMTD'],
            ['code' => 'COM', 'libelle' => 'Communication', 'direction' => 'DMTD'],
            ['code' => 'SI', 'libelle' => "Systèmes d'Informations", 'direction' => 'DMTD'],
            
            // DEW
            ['code' => 'ENT', 'libelle' => 'Entreprises', 'direction' => 'DEW'],
            ['code' => 'WHSL', 'libelle' => 'Wholesale', 'direction' => 'DEW'],
            
            // DJR
            ['code' => 'JUR', 'libelle' => 'Juridique', 'direction' => 'DJR'],
            
            // DOM
            ['code' => 'ENERGY', 'libelle' => 'Energie', 'direction' => 'DOM'],
            ['code' => 'MAINT', 'libelle' => 'Maintenance & Patrimoine', 'direction' => 'DOM'],
            ['code' => 'NOC', 'libelle' => 'NOC', 'direction' => 'DOM'],
            
            // DT
            ['code' => 'TRANSM', 'libelle' => 'Transmission', 'direction' => 'DT'],
            ['code' => 'SIPT', 'libelle' => 'SIPT', 'direction' => 'DT'],
            ['code' => 'IP_TEL', 'libelle' => 'IP & Telematique', 'direction' => 'DT'],
            
            // QHSE
            ['code' => 'HSE', 'libelle' => 'HSE', 'direction' => 'QHSE'],
            
            // DEC
            ['code' => 'EXP_CLI', 'libelle' => 'Expérience Client', 'direction' => 'DEC'],
            ['code' => 'RECOUV', 'libelle' => 'Recouvrement & Facturation', 'direction' => 'DEC'],
            ['code' => 'SRV_FIX', 'libelle' => 'Services Fixes', 'direction' => 'DEC'],
            
            // DVD
            ['code' => 'VENTE', 'libelle' => 'Vente', 'direction' => 'DVD'],
            ['code' => 'DISTRIB', 'libelle' => 'Distribution', 'direction' => 'DVD'],
            ['code' => 'COMMERCIAL', 'libelle' => 'Commercial', 'direction' => 'DVD'],
            
            // DCM
            ['code' => 'DCH', 'libelle' => 'DCH', 'direction' => 'DCM'],
        ];

        $ordre = 1;
        foreach ($services as $svc) {
            if (isset($directionIds[$svc['direction']])) {
                DB::table('services')->insert([
                    'id' => Str::uuid(),
                    'direction_id' => $directionIds[$svc['direction']],
                    'code' => $svc['code'],
                    'libelle' => $svc['libelle'],
                    'ordre_affichage' => $ordre++,
                    'actif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
