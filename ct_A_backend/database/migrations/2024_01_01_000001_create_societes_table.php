<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('societes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique();
            $table->string('raison_sociale', 200);
            $table->string('sigle', 20)->nullable();
            $table->string('forme_juridique', 50)->nullable();
            $table->string('niu', 15)->nullable()->unique()->comment('Numéro Identification Unique');
            $table->string('rccm', 30)->nullable()->unique()->comment('Registre Commerce et Crédit Mobilier');
            $table->text('adresse_siege')->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('boite_postale', 20)->nullable();
            $table->string('pays', 3)->default('COG');
            $table->string('telephone', 20)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('site_web', 300)->nullable();
            $table->string('devise_defaut', 3)->default('XAF');
            $table->string('logo_url', 500)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('societes');
    }
};
