<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SocieteSeeder::class,
            ZonesDirectionsServicesSeeder::class,
            RolesSeeder::class,
            UsersSeeder::class,
            UnitesMesureSeeder::class,
            NaturesDepenseSeeder::class,
        ]);
    }
}
