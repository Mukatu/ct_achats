<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $serviceAchats = DB::table('services')->where('code', 'ACH')->first();
        $serviceCDG = DB::table('services')->where('code', 'CDG')->first();
        $serviceTreso = DB::table('services')->where('code', 'TRESO')->first();
        
        $roleAdmin = DB::table('roles')->where('code', 'ADMIN')->first();
        $roleAcheteur = DB::table('roles')->where('code', 'ACHETEUR')->first();
        $roleCDG = DB::table('roles')->where('code', 'VALIDEUR_CDG')->first();
        $roleDFC = DB::table('roles')->where('code', 'VALIDEUR_DFC')->first();
        $roleDG = DB::table('roles')->where('code', 'VALIDEUR_DG')->first();
        $roleTresorier = DB::table('roles')->where('code', 'TRESORIER')->first();

        // Créer l'admin
        $adminId = Str::uuid()->toString();
        DB::table('users')->insert([
            'id' => $adminId,
            'matricule' => 'ADM001',
            'nom' => 'ADMIN',
            'prenom' => 'Super',
            'email' => 'admin@congotelecom.cg',
            'password' => Hash::make('Admin@2025!'),
            'service_id' => $serviceAchats?->id,
            'est_acheteur' => false,
            'est_valideur' => true,
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assigner le rôle admin
        if ($roleAdmin) {
            DB::table('role_user')->insert([
                'id' => Str::uuid(),
                'user_id' => $adminId,
                'role_id' => $roleAdmin->id,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Utilisateurs acheteurs (extraits du fichier Excel)
        $acheteurs = [
            ['prenom' => 'Ferlez', 'nom' => 'BUYER', 'email' => 'ferlez@congotelecom.cg', 'matricule' => 'ACH001'],
            ['prenom' => 'Judith', 'nom' => 'BUYER', 'email' => 'judith@congotelecom.cg', 'matricule' => 'ACH002'],
            ['prenom' => 'Leroy', 'nom' => 'BUYER', 'email' => 'leroy@congotelecom.cg', 'matricule' => 'ACH003'],
            ['prenom' => 'Sylvie', 'nom' => 'BUYER', 'email' => 'sylvie@congotelecom.cg', 'matricule' => 'ACH004'],
            ['prenom' => 'Rodney', 'nom' => 'BUYER', 'email' => 'rodney@congotelecom.cg', 'matricule' => 'ACH005'],
            ['prenom' => 'Wilfride', 'nom' => 'BUYER', 'email' => 'wilfride@congotelecom.cg', 'matricule' => 'ACH006'],
        ];

        foreach ($acheteurs as $acheteur) {
            $userId = Str::uuid()->toString();
            DB::table('users')->insert([
                'id' => $userId,
                'matricule' => $acheteur['matricule'],
                'nom' => $acheteur['nom'],
                'prenom' => $acheteur['prenom'],
                'email' => $acheteur['email'],
                'password' => Hash::make('Acheteur@2025!'),
                'service_id' => $serviceAchats?->id,
                'est_acheteur' => true,
                'est_valideur' => false,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assigner le rôle acheteur
            if ($roleAcheteur) {
                DB::table('role_user')->insert([
                    'id' => Str::uuid(),
                    'user_id' => $userId,
                    'role_id' => $roleAcheteur->id,
                    'actif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Valideur CDG
        $cdgId = Str::uuid()->toString();
        DB::table('users')->insert([
            'id' => $cdgId,
            'matricule' => 'CDG001',
            'nom' => 'CONTROLEUR',
            'prenom' => 'CDG',
            'email' => 'cdg@congotelecom.cg',
            'password' => Hash::make('Cdg@2025!'),
            'service_id' => $serviceCDG?->id,
            'est_acheteur' => false,
            'est_valideur' => true,
            'seuil_validation' => 50000000, // 50 millions XAF
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($roleCDG) {
            DB::table('role_user')->insert([
                'id' => Str::uuid(),
                'user_id' => $cdgId,
                'role_id' => $roleCDG->id,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Valideur DFC
        $dfcId = Str::uuid()->toString();
        DB::table('users')->insert([
            'id' => $dfcId,
            'matricule' => 'DFC001',
            'nom' => 'DIRECTEUR',
            'prenom' => 'Financier',
            'email' => 'dfc@congotelecom.cg',
            'password' => Hash::make('Dfc@2025!'),
            'service_id' => $serviceCDG?->id,
            'est_acheteur' => false,
            'est_valideur' => true,
            'seuil_validation' => 200000000, // 200 millions XAF
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($roleDFC) {
            DB::table('role_user')->insert([
                'id' => Str::uuid(),
                'user_id' => $dfcId,
                'role_id' => $roleDFC->id,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Trésorier
        $tresoId = Str::uuid()->toString();
        DB::table('users')->insert([
            'id' => $tresoId,
            'matricule' => 'TRS001',
            'nom' => 'TRESORIER',
            'prenom' => 'Chef',
            'email' => 'tresorerie@congotelecom.cg',
            'password' => Hash::make('Treso@2025!'),
            'service_id' => $serviceTreso?->id,
            'est_acheteur' => false,
            'est_valideur' => true,
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($roleTresorier) {
            DB::table('role_user')->insert([
                'id' => Str::uuid(),
                'user_id' => $tresoId,
                'role_id' => $roleTresorier->id,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
