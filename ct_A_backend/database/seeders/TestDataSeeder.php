<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Societe;
use App\Models\Zone;
use App\Models\Direction;
use App\Models\User;
use App\Models\Fournisseur;
use App\Models\ExpressionBesoin;
use App\Models\DemandeAchat;
use App\Models\BonCommande;
use App\Enums\StatutFournisseur;
use App\Enums\TypeFournisseur;
use App\Enums\StatutEB;
use App\Enums\StatutDA;
use App\Enums\StatutBC;
use App\Enums\TypeDemande;
use App\Enums\TypeBC;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $societe = Societe::first();
        $zone = Zone::first();
        $directions = Direction::all();
        $users = User::all();
        $admin = User::where('email', 'admin@congotelecom.cg')->first();

        // Créer des fournisseurs
        $fournisseurs = [
            [
                'code' => 'FOURN001',
                'raison_sociale' => 'CONGO FOURNITURES SARL',
                'sigle' => 'CF',
                'forme_juridique' => 'SARL',
                'niu' => 'M2024110001',
                'rccm' => 'CG-BZV-01-2024-A00001',
                'adresse' => 'Avenue de la Paix, Brazzaville',
                'ville' => 'Brazzaville',
                'pays' => 'COG',
                'telephone' => '+242 06 500 00 01',
                'email' => 'contact@congofournitures.cg',
                'type_fournisseur' => TypeFournisseur::LOCAL,
                'statut' => StatutFournisseur::ACTIF,
                'devise_defaut' => 'XAF',
                'taux_tva' => 18.00,
                'assujetti_tva' => true,
            ],
            [
                'code' => 'FOURN002',
                'raison_sociale' => 'TECH SOLUTIONS CONGO SA',
                'sigle' => 'TSC',
                'forme_juridique' => 'SA',
                'niu' => 'M2024110002',
                'rccm' => 'CG-BZV-01-2024-A00002',
                'adresse' => 'Boulevard Denis Sassou Nguesso',
                'ville' => 'Brazzaville',
                'pays' => 'COG',
                'telephone' => '+242 05 600 00 02',
                'email' => 'info@techsolutions.cg',
                'type_fournisseur' => TypeFournisseur::LOCAL,
                'statut' => StatutFournisseur::ACTIF,
                'devise_defaut' => 'XAF',
                'taux_tva' => 18.00,
                'assujetti_tva' => true,
            ],
            [
                'code' => 'FOURN003',
                'raison_sociale' => 'HUAWEI TECHNOLOGIES CEMAC',
                'sigle' => 'HUAWEI',
                'forme_juridique' => 'SA',
                'niu' => 'M2024110003',
                'rccm' => 'CG-BZV-01-2024-A00003',
                'adresse' => 'Centre-ville, Douala',
                'ville' => 'Douala',
                'pays' => 'CMR',
                'telephone' => '+237 699 000 003',
                'email' => 'cemac@huawei.com',
                'type_fournisseur' => TypeFournisseur::CEMAC,
                'statut' => StatutFournisseur::ACTIF,
                'devise_defaut' => 'XAF',
                'taux_tva' => 18.00,
                'assujetti_tva' => true,
            ],
            [
                'code' => 'FOURN004',
                'raison_sociale' => 'NOKIA NETWORKS FRANCE',
                'sigle' => 'NOKIA',
                'forme_juridique' => 'SAS',
                'adresse' => 'Paris La Défense',
                'ville' => 'Paris',
                'pays' => 'FRA',
                'telephone' => '+33 1 40 00 00 04',
                'email' => 'africa@nokia.com',
                'type_fournisseur' => TypeFournisseur::INTERNATIONAL,
                'statut' => StatutFournisseur::ACTIF,
                'devise_defaut' => 'EUR',
                'taux_tva' => 0,
                'assujetti_tva' => false,
            ],
            [
                'code' => 'FOURN005',
                'raison_sociale' => 'BUREAUTIQUE CONGO',
                'sigle' => 'BC',
                'forme_juridique' => 'SARL',
                'niu' => 'M2024110005',
                'rccm' => 'CG-BZV-01-2024-A00005',
                'adresse' => 'Rue Matsiona, Bacongo',
                'ville' => 'Brazzaville',
                'pays' => 'COG',
                'telephone' => '+242 06 700 00 05',
                'email' => 'contact@bureautiquecongo.cg',
                'type_fournisseur' => TypeFournisseur::LOCAL,
                'statut' => StatutFournisseur::ACTIF,
                'devise_defaut' => 'XAF',
                'taux_tva' => 18.00,
                'assujetti_tva' => true,
            ],
        ];

        $createdFournisseurs = [];
        foreach ($fournisseurs as $data) {
            $createdFournisseurs[] = Fournisseur::create(array_merge($data, [
                'societe_id' => $societe->id,
                'actif' => true,
                'created_by' => $admin->id,
            ]));
        }

        $this->command->info('5 fournisseurs créés');

        // Créer des Expressions de Besoins
        $ebData = [
            [
                'objet' => 'Acquisition de 20 ordinateurs portables HP ProBook',
                'description_detaillee' => 'Remplacement du parc informatique vétuste pour le service marketing. Configuration minimale: i5, 16Go RAM, 512Go SSD',
                'estimation' => 15000000,
                'statut' => StatutEB::EN_SUSPENS,
            ],
            [
                'objet' => 'Fourniture de câbles fibre optique monomode 100km',
                'description_detaillee' => 'Extension du réseau fibre vers la zone de Pointe-Noire. Câble OS2 9/125µm',
                'estimation' => 45000000,
                'statut' => StatutEB::EN_COURS_ACH,
            ],
            [
                'objet' => 'Achat de mobilier de bureau pour nouveau siège',
                'description_detaillee' => '50 bureaux, 50 fauteuils ergonomiques, 10 salles de réunion complètes',
                'estimation' => 35000000,
                'statut' => StatutEB::EN_COURS_CDG,
            ],
            [
                'objet' => 'Maintenance préventive climatisation datacenter',
                'description_detaillee' => 'Contrat annuel de maintenance des 12 climatiseurs de précision du datacenter',
                'estimation' => 8500000,
                'statut' => StatutEB::TRAITE,
            ],
            [
                'objet' => 'Acquisition de 5 véhicules utilitaires',
                'description_detaillee' => 'Renouvellement de la flotte véhicules pour les équipes techniques. Pick-up 4x4 double cabine',
                'estimation' => 125000000,
                'statut' => StatutEB::EN_COURS_DFC,
            ],
            [
                'objet' => 'Fournitures de bureau pour exercice 2026',
                'description_detaillee' => 'Papier, stylos, toners, consommables divers pour l\'ensemble des directions',
                'estimation' => 4500000,
                'statut' => StatutEB::EN_COURS_ACH,
            ],
            [
                'objet' => 'Équipement réseau Switches Cisco',
                'description_detaillee' => '20 switches Cisco Catalyst 9200 pour rénovation infrastructure LAN',
                'estimation' => 28000000,
                'statut' => StatutEB::EN_SUSPENS,
            ],
            [
                'objet' => 'Campagne publicitaire télévision Q1 2026',
                'description_detaillee' => 'Spots TV sur les chaînes nationales - promotion offres mobile',
                'estimation' => 55000000,
                'statut' => StatutEB::EN_COURS_DG,
            ],
        ];

        $annee = now()->format('y');
        $numSequence = 1;

        foreach ($ebData as $index => $data) {
            $direction = $directions->random();
            $demandeur = $users->random();
            $acheteur = User::where('est_acheteur', true)->inRandomOrder()->first() ?? $admin;

            ExpressionBesoin::create([
                'societe_id' => $societe->id,
                'numero' => str_pad($numSequence++, 4, '0', STR_PAD_LEFT) . "/{$annee} - EB",
                'date_expression' => now()->subDays(rand(1, 30)),
                'zone_id' => $zone->id,
                'direction_id' => $direction->id,
                'demandeur_id' => $demandeur->id,
                'acheteur_id' => $data['statut'] !== StatutEB::EN_SUSPENS ? $acheteur->id : null,
                'objet' => $data['objet'],
                'description_detaillee' => $data['description_detaillee'],
                'estimation' => $data['estimation'],
                'statut' => $data['statut'],
                'created_by' => $demandeur->id,
            ]);
        }

        $this->command->info('8 expressions de besoins créées');

        // Créer des Demandes d'Achat
        $daData = [
            [
                'objet' => 'Maintenance préventive climatisation datacenter',
                'montant' => 8500000,
                'type_demande' => TypeDemande::DA,
                'statut' => StatutDA::EN_COURS_CDG,
            ],
            [
                'objet' => 'Fournitures de bureau - Direction Marketing',
                'montant' => 1500000,
                'type_demande' => TypeDemande::DAC,
                'statut' => StatutDA::TRAITE,
            ],
            [
                'objet' => 'Licence logiciel antivirus Kaspersky',
                'montant' => 3200000,
                'type_demande' => TypeDemande::DA,
                'statut' => StatutDA::EN_COURS_ACH,
            ],
        ];

        $numSequenceDA = 1;
        foreach ($daData as $data) {
            $direction = $directions->random();
            $demandeur = $users->random();
            $acheteur = User::where('est_acheteur', true)->inRandomOrder()->first() ?? $admin;
            $suffix = $data['type_demande'] === TypeDemande::DAC ? 'DAC' : 'DA';

            DemandeAchat::create([
                'societe_id' => $societe->id,
                'numero' => str_pad($numSequenceDA++, 4, '0', STR_PAD_LEFT) . "/{$annee} - {$suffix}",
                'type_demande' => $data['type_demande'],
                'date_demande' => now()->subDays(rand(1, 20)),
                'zone_id' => $zone->id,
                'direction_id' => $direction->id,
                'demandeur_id' => $demandeur->id,
                'acheteur_id' => $acheteur->id,
                'objet' => $data['objet'],
                'montant' => $data['montant'],
                'statut' => $data['statut'],
                'created_by' => $demandeur->id,
            ]);
        }

        $this->command->info('3 demandes d\'achat créées');

        // Créer des Bons de Commande
        $bcData = [
            [
                'objet' => 'Maintenance climatisation datacenter - Contrat annuel',
                'montant_ht_xaf' => 7203389,
                'type_bc' => TypeBC::BCAL,
                'statut' => StatutBC::EN_COURS_FSSEUR,
                'fournisseur_index' => 0,
            ],
            [
                'objet' => 'Équipements réseau Huawei - Switches access',
                'montant_ht_xaf' => 25000000,
                'type_bc' => TypeBC::BCAL,
                'statut' => StatutBC::LIVRE,
                'fournisseur_index' => 2,
            ],
            [
                'objet' => 'Solution Nokia 5G Core - Phase pilote',
                'montant_ht_xaf' => 328978500, // 500 000 EUR
                'montant_devise' => 500000,
                'devise_etrangere' => 'EUR',
                'taux_change' => 655.957,
                'type_bc' => TypeBC::IPO,
                'statut' => StatutBC::EN_COURS_DFC,
                'fournisseur_index' => 3,
            ],
        ];

        $numSequenceBC = 1;
        foreach ($bcData as $data) {
            $direction = $directions->random();
            $demandeur = $users->random();
            $acheteur = User::where('est_acheteur', true)->inRandomOrder()->first() ?? $admin;
            $fournisseur = $createdFournisseurs[$data['fournisseur_index']];

            $montantHT = $data['montant_ht_xaf'];
            $tauxTVA = $fournisseur->taux_tva;
            $montantTVA = $montantHT * ($tauxTVA / 100);
            $montantTTC = $montantHT + $montantTVA;

            BonCommande::create([
                'societe_id' => $societe->id,
                'numero' => str_pad($numSequenceBC++, 3, '0', STR_PAD_LEFT) . "/{$annee}",
                'type_bc' => $data['type_bc'],
                'date_bc' => now()->subDays(rand(1, 15)),
                'fournisseur_id' => $fournisseur->id,
                'zone_id' => $zone->id,
                'direction_id' => $direction->id,
                'demandeur_id' => $demandeur->id,
                'acheteur_id' => $acheteur->id,
                'objet' => $data['objet'],
                'montant_ht_xaf' => $montantHT,
                'taux_tva' => $tauxTVA,
                'montant_tva' => $montantTVA,
                'montant_ttc_xaf' => $montantTTC,
                'devise_etrangere' => $data['devise_etrangere'] ?? null,
                'montant_devise' => $data['montant_devise'] ?? null,
                'taux_change' => $data['taux_change'] ?? null,
                'statut' => $data['statut'],
                'created_by' => $acheteur->id,
            ]);
        }

        $this->command->info('3 bons de commande créés');
        $this->command->info('Données de test créées avec succès!');
    }
}
