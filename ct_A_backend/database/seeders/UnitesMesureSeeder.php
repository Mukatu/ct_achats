<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnitesMesureSeeder extends Seeder
{
    public function run(): void
    {
        $unites = [
            // Quantité
            ['code' => 'U', 'libelle' => 'Unité', 'symbole' => 'U', 'type' => 'QUANTITE'],
            ['code' => 'PCE', 'libelle' => 'Pièce', 'symbole' => 'pce', 'type' => 'QUANTITE'],
            ['code' => 'LOT', 'libelle' => 'Lot', 'symbole' => 'lot', 'type' => 'QUANTITE'],
            ['code' => 'BTE', 'libelle' => 'Boîte', 'symbole' => 'bte', 'type' => 'QUANTITE'],
            ['code' => 'CAR', 'libelle' => 'Carton', 'symbole' => 'car', 'type' => 'QUANTITE'],
            ['code' => 'PAL', 'libelle' => 'Palette', 'symbole' => 'pal', 'type' => 'QUANTITE'],
            
            // Poids
            ['code' => 'KG', 'libelle' => 'Kilogramme', 'symbole' => 'kg', 'type' => 'POIDS'],
            ['code' => 'G', 'libelle' => 'Gramme', 'symbole' => 'g', 'type' => 'POIDS'],
            ['code' => 'T', 'libelle' => 'Tonne', 'symbole' => 't', 'type' => 'POIDS'],
            
            // Longueur
            ['code' => 'M', 'libelle' => 'Mètre', 'symbole' => 'm', 'type' => 'LONGUEUR'],
            ['code' => 'KM', 'libelle' => 'Kilomètre', 'symbole' => 'km', 'type' => 'LONGUEUR'],
            ['code' => 'CM', 'libelle' => 'Centimètre', 'symbole' => 'cm', 'type' => 'LONGUEUR'],
            ['code' => 'ML', 'libelle' => 'Mètre linéaire', 'symbole' => 'ml', 'type' => 'LONGUEUR'],
            
            // Surface
            ['code' => 'M2', 'libelle' => 'Mètre carré', 'symbole' => 'm²', 'type' => 'SURFACE'],
            
            // Volume
            ['code' => 'L', 'libelle' => 'Litre', 'symbole' => 'L', 'type' => 'VOLUME'],
            ['code' => 'M3', 'libelle' => 'Mètre cube', 'symbole' => 'm³', 'type' => 'VOLUME'],
            
            // Temps
            ['code' => 'H', 'libelle' => 'Heure', 'symbole' => 'h', 'type' => 'TEMPS'],
            ['code' => 'J', 'libelle' => 'Jour', 'symbole' => 'j', 'type' => 'TEMPS'],
            ['code' => 'SEM', 'libelle' => 'Semaine', 'symbole' => 'sem', 'type' => 'TEMPS'],
            ['code' => 'MOIS', 'libelle' => 'Mois', 'symbole' => 'mois', 'type' => 'TEMPS'],
            ['code' => 'AN', 'libelle' => 'Année', 'symbole' => 'an', 'type' => 'TEMPS'],
            
            // Forfait
            ['code' => 'FORF', 'libelle' => 'Forfait', 'symbole' => 'forf.', 'type' => null],
            ['code' => 'ENS', 'libelle' => 'Ensemble', 'symbole' => 'ens.', 'type' => null],
        ];

        foreach ($unites as $unite) {
            DB::table('unites_mesure')->insert([
                'id' => Str::uuid(),
                'code' => $unite['code'],
                'libelle' => $unite['libelle'],
                'symbole' => $unite['symbole'],
                'type' => $unite['type'],
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
