<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\ExpressionBesoin;
use App\Models\DemandeAchat;
use App\Models\BonCommande;
use App\Models\Fournisseur;
use App\Models\Reception;
use App\Models\LigneReception;
use App\Models\LigneBonCommande;

class ClearTestData extends Command
{
    protected $signature = 'data:clear
                            {--force : Supprimer sans confirmation}
                            {--keep-fournisseurs : Garder les fournisseurs}
                            {--reset-sequences : Remettre les compteurs à zéro}';

    protected $description = 'Supprime les données transactionnelles (EB, DA, BC) pour permettre l\'import de données réelles';

    public function handle()
    {
        $this->warn('⚠️  ATTENTION: Cette commande va supprimer les données suivantes:');
        $this->newLine();

        // Compter les données existantes
        $counts = [
            'Expressions de Besoins (EB)' => ExpressionBesoin::count(),
            'Demandes d\'Achat (DA/DAC)' => DemandeAchat::count(),
            'Bons de Commande (BC)' => BonCommande::count(),
            'Réceptions' => Reception::count(),
        ];

        if (!$this->option('keep-fournisseurs')) {
            $counts['Fournisseurs'] = Fournisseur::count();
        }

        $this->table(['Type de données', 'Nombre'], collect($counts)->map(fn($count, $type) => [$type, $count])->toArray());

        if (!$this->option('force')) {
            if (!$this->confirm('Êtes-vous sûr de vouloir supprimer ces données?')) {
                $this->info('Opération annulée.');
                return 0;
            }
        }

        $this->info('Suppression en cours...');

        DB::beginTransaction();

        try {
            // Désactiver les contraintes de clé étrangère temporairement
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Supprimer dans l'ordre des dépendances
            $deleted = [];

            // 1. Lignes de réception
            if (class_exists(LigneReception::class)) {
                $deleted['Lignes de réception'] = LigneReception::query()->delete();
            }

            // 2. Réceptions
            $deleted['Réceptions'] = Reception::query()->delete();

            // 3. Lignes de BC
            if (class_exists(LigneBonCommande::class)) {
                $deleted['Lignes BC'] = LigneBonCommande::query()->delete();
            }

            // 4. Bons de Commande
            $deleted['Bons de Commande'] = BonCommande::query()->delete();

            // 5. Demandes d'Achat
            $deleted['Demandes d\'Achat'] = DemandeAchat::query()->delete();

            // 6. Expressions de Besoins
            $deleted['Expressions de Besoins'] = ExpressionBesoin::query()->delete();

            // 7. Fournisseurs (optionnel)
            if (!$this->option('keep-fournisseurs')) {
                $deleted['Fournisseurs'] = Fournisseur::query()->delete();
            }

            // Réactiver les contraintes
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // Remettre les compteurs à zéro si demandé
            if ($this->option('reset-sequences')) {
                $this->resetSequences();
                $this->info('Compteurs de numérotation remis à zéro.');
            }

            DB::commit();

            $this->newLine();
            $this->info('✅ Données supprimées avec succès:');
            $this->table(['Type', 'Supprimés'], collect($deleted)->map(fn($count, $type) => [$type, $count])->toArray());

            $this->newLine();
            $this->info('Vous pouvez maintenant importer vos données réelles.');

        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->error('Erreur lors de la suppression: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    protected function resetSequences(): void
    {
        // Remettre les compteurs de numérotation à zéro
        DB::table('numerotations')->update([
            'dernier_numero' => 0,
        ]);
    }
}
