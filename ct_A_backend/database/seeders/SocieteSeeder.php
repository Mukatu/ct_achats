<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocieteSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('societes')->insert([
            'id' => Str::uuid(),
            'code' => 'CT',
            'raison_sociale' => 'Congo Telecom',
            'sigle' => 'CT',
            'forme_juridique' => 'SA',
            'niu' => null,
            'rccm' => null,
            'adresse_siege' => 'Brazzaville, République du Congo',
            'ville' => 'Brazzaville',
            'pays' => 'COG',
            'devise_defaut' => 'XAF',
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
