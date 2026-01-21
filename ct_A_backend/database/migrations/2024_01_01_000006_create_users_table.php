<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('matricule', 20)->nullable()->unique();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 200)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('telephone', 20)->nullable();
            $table->uuid('service_id')->nullable();
            $table->uuid('manager_id')->nullable();
            $table->boolean('est_acheteur')->default(false);
            $table->boolean('est_valideur')->default(false);
            $table->decimal('seuil_validation', 18, 0)->nullable()->comment('Seuil en XAF');
            $table->boolean('actif')->default(true);
            $table->date('date_entree')->nullable();
            $table->date('date_sortie')->nullable();
            $table->timestamp('derniere_connexion')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
        });

        // Table pivot utilisateur_role
        Schema::create('role_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->uuid('zone_id')->nullable()->comment('Périmètre zone');
            $table->uuid('direction_id')->nullable()->comment('Périmètre direction');
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('zone_id')->references('id')->on('zones')->nullOnDelete();
            $table->foreign('direction_id')->references('id')->on('directions')->nullOnDelete();
            
            $table->unique(['user_id', 'role_id', 'zone_id', 'direction_id'], 'role_user_unique');
        });

        // Ajouter les FK responsable après création de users
        Schema::table('zones', function (Blueprint $table) {
            $table->foreign('responsable_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('directions', function (Blueprint $table) {
            $table->foreign('responsable_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreign('responsable_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['responsable_id']);
        });
        Schema::table('directions', function (Blueprint $table) {
            $table->dropForeign(['responsable_id']);
        });
        Schema::table('zones', function (Blueprint $table) {
            $table->dropForeign(['responsable_id']);
        });
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('users');
    }
};
