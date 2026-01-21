<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Fournisseur;
use App\Models\Societe;
use App\Models\User;
use App\Enums\StatutFournisseur;
use App\Enums\TypeFournisseur;

class CreateFournisseurs extends Command
{
    protected $signature = 'fournisseurs:create {--force : Créer même si déjà existants}';

    protected $description = 'Crée les fournisseurs extraits du fichier source';

    protected array $fournisseurs = [
        [
            'code' => 'TOTAL-E',
            'raison_sociale' => 'Total Energies',
            'sigle' => 'Total E',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'SPORAFRIC',
            'raison_sociale' => 'Sporafric SARL',
            'sigle' => 'Sporafric',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'GTEC',
            'raison_sociale' => 'Société GTEC',
            'sigle' => 'Ste GTEC',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'WECOM',
            'raison_sociale' => 'Wecom Congo',
            'sigle' => 'Wecom',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'BUROTEC',
            'raison_sociale' => 'Burotec Congo',
            'sigle' => 'Burotec',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'TRANSIT-EXP',
            'raison_sociale' => 'Transit Express',
            'sigle' => 'Transit Express',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'SATGURU',
            'raison_sociale' => 'Satguru Travel',
            'sigle' => 'Satguru',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'HARIOM',
            'raison_sociale' => 'Hariom Voyages',
            'sigle' => 'Hariom',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'AERCO',
            'raison_sociale' => 'Aéroport de Brazzaville (AERCO)',
            'sigle' => 'Aerco',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'VISIONA',
            'raison_sociale' => 'Visiona Communication',
            'sigle' => 'Visiona',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'E2C',
            'raison_sociale' => 'E2C - Energie Electrique du Congo',
            'sigle' => 'E2C',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'SCI-RR',
            'raison_sociale' => 'SCI Rivière Rouge',
            'sigle' => 'SCI Rivière Rouge',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'TRESOR',
            'raison_sociale' => 'Trésor Public',
            'sigle' => 'Tresor Public',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'EDT',
            'raison_sociale' => 'EDT - Établissement de Timbres',
            'sigle' => 'EDT',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'OTIELI',
            'raison_sociale' => 'Maître Otieli - Cabinet d\'Avocat',
            'sigle' => 'Maitre Otieli',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'LCR',
            'raison_sociale' => 'LCR Congo',
            'sigle' => 'LCR',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'DUREL',
            'raison_sociale' => 'Durel Services',
            'sigle' => 'Durel Services',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'TRANS-BONY',
            'raison_sociale' => 'Trans Bony',
            'sigle' => 'Trans Bony',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'CRISP',
            'raison_sociale' => 'Crisp n Croc',
            'sigle' => 'Crisp n croc',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
        [
            'code' => 'DIVERS',
            'raison_sociale' => 'Fournisseurs Divers',
            'sigle' => 'Divers',
            'type' => TypeFournisseur::LOCAL,
            'ville' => 'Brazzaville',
        ],
    ];

    public function handle()
    {
        $societe = Societe::first();
        $admin = User::first();

        if (!$societe || !$admin) {
            $this->error('Société ou utilisateur admin non trouvé.');
            return 1;
        }

        $this->info('Création des fournisseurs...');
        $created = 0;
        $skipped = 0;

        foreach ($this->fournisseurs as $data) {
            // Vérifier si le fournisseur existe déjà (par code ou sigle)
            $exists = Fournisseur::where('code', $data['code'])
                ->orWhere('sigle', $data['sigle'])
                ->orWhere('raison_sociale', $data['raison_sociale'])
                ->first();

            if ($exists && !$this->option('force')) {
                $this->line("  <comment>Ignoré:</comment> {$data['raison_sociale']} (existe déjà)");
                $skipped++;
                continue;
            }

            if ($exists && $this->option('force')) {
                // Mettre à jour le code si nécessaire
                $exists->update(['code' => $data['code']]);
                $this->line("  <info>Mis à jour:</info> {$data['raison_sociale']}");
                $skipped++;
                continue;
            }

            Fournisseur::create([
                'societe_id' => $societe->id,
                'code' => $data['code'],
                'raison_sociale' => $data['raison_sociale'],
                'sigle' => $data['sigle'],
                'forme_juridique' => 'SARL',
                'adresse' => 'Brazzaville',
                'ville' => $data['ville'],
                'pays' => 'COG',
                'type_fournisseur' => $data['type'],
                'statut' => StatutFournisseur::ACTIF,
                'devise_defaut' => 'XAF',
                'taux_tva' => 19.25,
                'assujetti_tva' => true,
                'actif' => true,
                'created_by' => $admin->id,
            ]);

            $this->line("  <info>Créé:</info> {$data['raison_sociale']} ({$data['code']})");
            $created++;
        }

        $this->newLine();
        $this->info("✅ Terminé: {$created} fournisseurs créés, {$skipped} ignorés/mis à jour.");

        // Afficher la table des fournisseurs
        $this->newLine();
        $this->table(
            ['Code', 'Sigle', 'Raison Sociale'],
            Fournisseur::select('code', 'sigle', 'raison_sociale')->get()->toArray()
        );

        return 0;
    }
}
