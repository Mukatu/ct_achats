<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('natures_depense')) {
            Schema::create('natures_depense', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 10)->unique();
                $table->string('libelle', 100);
                $table->string('compte_comptable', 20)->nullable();
                $table->boolean('actif')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });

            // Insérer des valeurs par défaut
            $natures = [
                ['code' => 'FOUR', 'libelle' => 'Fournitures de bureau'],
                ['code' => 'INFO', 'libelle' => 'Matériel informatique'],
                ['code' => 'MAINT', 'libelle' => 'Maintenance et réparations'],
                ['code' => 'PREST', 'libelle' => 'Prestations de services'],
                ['code' => 'FORM', 'libelle' => 'Formation'],
                ['code' => 'DEPL', 'libelle' => 'Déplacements et missions'],
                ['code' => 'TELECOM', 'libelle' => 'Télécommunications'],
                ['code' => 'ENERGIE', 'libelle' => 'Énergie et fluides'],
            ];

            foreach ($natures as $nature) {
                DB::table('natures_depense')->insert([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'code' => $nature['code'],
                    'libelle' => $nature['libelle'],
                    'actif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('natures_depense');
    }
};
