<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('code', 10);
            $table->string('libelle', 100);
            $table->string('ville', 100)->nullable();
            $table->text('adresse')->nullable();
            $table->string('telephone', 20)->nullable();
            $table->uuid('responsable_id')->nullable();
            $table->integer('ordre_affichage')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->unique(['societe_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
