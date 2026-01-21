<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insérer les valeurs par défaut
        $settings = [
            ['key' => 'nom_entreprise', 'value' => 'CAMTEL SA'],
            ['key' => 'devise', 'value' => 'XAF'],
            ['key' => 'seuil_chef_service', 'value' => '500000'],
            ['key' => 'seuil_directeur', 'value' => '5000000'],
            ['key' => 'seuil_dg', 'value' => '50000000'],
            ['key' => 'delai_validation_eb', 'value' => '48'],
            ['key' => 'delai_validation_da', 'value' => '72'],
            ['key' => 'delai_validation_bc', 'value' => '48'],
            ['key' => 'prefixe_eb', 'value' => 'EB'],
            ['key' => 'prefixe_da', 'value' => 'DA'],
            ['key' => 'prefixe_bc', 'value' => 'BC'],
            ['key' => 'prefixe_br', 'value' => 'BR'],
            ['key' => 'prefixe_facture', 'value' => 'FAC'],
            ['key' => 'longueur_numero', 'value' => '5'],
            ['key' => 'reinitialiser_annuellement', 'value' => '1'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'key' => $setting['key'],
                'value' => $setting['value'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
