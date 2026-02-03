<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExpressionBesoin;
use App\Models\DemandeAchat;
use App\Models\BonCommande;
use App\Enums\StatutEB;
use App\Enums\StatutDA;
use App\Enums\StatutBC;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CorrigerStatutsCommand extends Command
{
    protected $signature = 'achats:corriger-statuts
                            {--dry-run : Afficher les corrections sans les appliquer}
                            {--eb-only : Corriger uniquement les EB}
                            {--da-only : Corriger uniquement les DA}';

    protected $description = 'Corrige les statuts incohérents des EB et DA après import';

    protected int $ebCorrigees = 0;
    protected int $daCorrigees = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $ebOnly = $this->option('eb-only');
        $daOnly = $this->option('da-only');

        if ($dryRun) {
            $this->warn('Mode simulation activé - aucune modification ne sera effectuée');
        }

        $this->info('');
        $this->info('===========================================');
        $this->info('  Correction des statuts après import');
        $this->info('===========================================');
        $this->info('');

        DB::beginTransaction();

        try {
            // Corriger les EB (sauf si --da-only)
            if (!$daOnly) {
                $this->corrigerEB($dryRun);
            }

            // Corriger les DA (sauf si --eb-only)
            if (!$ebOnly) {
                $this->corrigerDA($dryRun);
            }

            if (!$dryRun) {
                DB::commit();
                $this->info('');
                $this->info('✓ Corrections appliquées avec succès');
            } else {
                DB::rollBack();
                $this->info('');
                $this->warn('Mode simulation - aucune modification appliquée');
            }

            // Résumé
            $this->info('');
            $this->table(
                ['Type', 'Corrigées'],
                [
                    ['Expressions de Besoins (EB)', $this->ebCorrigees],
                    ['Demandes d\'Achat (DA)', $this->daCorrigees],
                ]
            );

            // Logger les corrections
            if (!$dryRun && ($this->ebCorrigees > 0 || $this->daCorrigees > 0)) {
                Log::info('Correction des statuts effectuée', [
                    'eb_corrigees' => $this->ebCorrigees,
                    'da_corrigees' => $this->daCorrigees,
                    'user' => auth()->user()?->email ?? 'console',
                ]);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Erreur lors de la correction : ' . $e->getMessage());
            Log::error('Erreur correction statuts', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    /**
     * Corrige les EB qui ont une DA liée mais ne sont pas marquées TRAITE
     */
    protected function corrigerEB(bool $dryRun): void
    {
        $this->info('Analyse des Expressions de Besoins...');

        // EB qui ont une DA liée mais ne sont pas en statut TRAITE ou ANNULE
        $ebACorreger = ExpressionBesoin::whereNotIn('statut', [StatutEB::TRAITE, StatutEB::ANNULE])
            ->whereHas('demandesAchat')
            ->get();

        if ($ebACorreger->isEmpty()) {
            $this->info('  → Aucune EB à corriger');
            return;
        }

        $this->info("  → {$ebACorreger->count()} EB à corriger");

        $headers = ['Référence', 'Ancien statut', 'Nouveau statut', 'DA liée'];
        $rows = [];

        foreach ($ebACorreger as $eb) {
            $da = $eb->demandesAchat()->first();

            $rows[] = [
                $eb->numero,
                $eb->statut->label(),
                StatutEB::TRAITE->label(),
                $da?->numero ?? '-',
            ];

            if (!$dryRun) {
                $eb->update(['statut' => StatutEB::TRAITE]);
            }

            $this->ebCorrigees++;
        }

        $this->table($headers, $rows);
    }

    /**
     * Corrige les DA qui ont un BC lié mais ne sont pas marquées TRAITE
     */
    protected function corrigerDA(bool $dryRun): void
    {
        $this->info('');
        $this->info('Analyse des Demandes d\'Achat...');

        // DA (type DA, pas DAC) qui ont un BC lié non annulé mais ne sont pas en statut TRAITE ou ANNULE
        $daACorreger = DemandeAchat::where('type_demande', 'DA')
            ->whereNotIn('statut', [StatutDA::TRAITE, StatutDA::ANNULE])
            ->whereHas('bonsCommande', function ($query) {
                $query->where('statut', '!=', StatutBC::ANNULE);
            })
            ->get();

        if ($daACorreger->isEmpty()) {
            $this->info('  → Aucune DA à corriger');
            return;
        }

        $this->info("  → {$daACorreger->count()} DA à corriger");

        $headers = ['Référence', 'Ancien statut', 'Nouveau statut', 'BC lié'];
        $rows = [];

        foreach ($daACorreger as $da) {
            $bc = $da->bonsCommande()->where('statut', '!=', StatutBC::ANNULE)->first();

            $rows[] = [
                $da->numero,
                $da->statut->label(),
                StatutDA::TRAITE->label(),
                $bc?->numero ?? '-',
            ];

            if (!$dryRun) {
                $da->update(['statut' => StatutDA::TRAITE]);
            }

            $this->daCorrigees++;
        }

        $this->table($headers, $rows);
    }
}
