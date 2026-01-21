<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('zone_id');
            $table->string('code', 10);
            $table->string('libelle', 200);
            $table->string('libelle_court', 50)->nullable();
            $table->text('description')->nullable();
            $table->uuid('responsable_id')->nullable();
            $table->integer('ordre_affichage')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('zone_id')->references('id')->on('zones')->onDelete('cascade');
            $table->unique(['zone_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directions');
    }
};
