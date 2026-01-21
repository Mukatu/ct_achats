<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'code' => 'ADMIN',
                'libelle' => 'Administrateur',
                'description' => 'Accès complet à toutes les fonctionnalités',
                'permissions' => json_encode([
                    'all' => true
                ]),
                'est_systeme' => true,
            ],
            [
                'code' => 'DEMANDEUR',
                'libelle' => 'Demandeur',
                'description' => 'Peut créer des expressions de besoins',
                'permissions' => json_encode([
                    'eb.create', 'eb.view', 'eb.edit_own',
                    'da.view_own', 'bc.view_own'
                ]),
                'est_systeme' => true,
            ],
            [
                'code' => 'ACHETEUR',
                'libelle' => 'Acheteur',
                'description' => 'Gère les demandes d\'achat et bons de commande',
                'permissions' => json_encode([
                    'eb.view', 'eb.assign',
                    'da.create', 'da.view', 'da.edit', 'da.validate_ach',
                    'bc.create', 'bc.view', 'bc.edit', 'bc.send',
                    'fournisseur.view', 'fournisseur.create', 'fournisseur.edit'
                ]),
                'est_systeme' => true,
            ],
            [
                'code' => 'VALIDEUR_CDG',
                'libelle' => 'Valideur Contrôle de Gestion',
                'description' => 'Valide les demandes au niveau CDG',
                'permissions' => json_encode([
                    'eb.view', 'da.view', 'da.validate_cdg',
                    'bc.view', 'bc.validate_cdg',
                    'budget.view'
                ]),
                'est_systeme' => true,
            ],
            [
                'code' => 'VALIDEUR_DFC',
                'libelle' => 'Valideur DFC',
                'description' => 'Valide les demandes de montant élevé',
                'permissions' => json_encode([
                    'eb.view', 'da.view', 'da.validate_dfc',
                    'bc.view', 'bc.validate_dfc',
                    'budget.view', 'budget.manage'
                ]),
                'est_systeme' => true,
            ],
            [
                'code' => 'VALIDEUR_DG',
                'libelle' => 'Valideur Direction Générale',
                'description' => 'Valide les demandes de très haut montant',
                'permissions' => json_encode([
                    'eb.view', 'da.view', 'da.validate_dg',
                    'bc.view', 'bc.validate_dg',
                    'all.view'
                ]),
                'est_systeme' => true,
            ],
            [
                'code' => 'TRESORIER',
                'libelle' => 'Trésorier',
                'description' => 'Gère les paiements et DAC',
                'permissions' => json_encode([
                    'da.view', 'dac.process',
                    'bc.view',
                    'facture.view', 'facture.paiement'
                ]),
                'est_systeme' => true,
            ],
            [
                'code' => 'COMPTABLE',
                'libelle' => 'Comptable',
                'description' => 'Gère les factures et rapprochements',
                'permissions' => json_encode([
                    'bc.view',
                    'reception.view',
                    'facture.create', 'facture.view', 'facture.edit', 'facture.rapprocher'
                ]),
                'est_systeme' => true,
            ],
            [
                'code' => 'RECEPTIONNAIRE',
                'libelle' => 'Réceptionnaire',
                'description' => 'Enregistre les réceptions',
                'permissions' => json_encode([
                    'bc.view',
                    'reception.create', 'reception.view', 'reception.edit'
                ]),
                'est_systeme' => false,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insert([
                'id' => Str::uuid(),
                'code' => $role['code'],
                'libelle' => $role['libelle'],
                'description' => $role['description'],
                'permissions' => $role['permissions'],
                'est_systeme' => $role['est_systeme'],
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
