<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catégories fournisseurs
        Schema::create('categories_fournisseur', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('code', 20);
            $table->string('libelle', 200);
            $table->text('description')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->integer('niveau')->default(1);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('categories_fournisseur')->nullOnDelete();
            $table->unique(['societe_id', 'code']);
        });

        // Fournisseurs
        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('code', 20);
            $table->string('raison_sociale', 200);
            $table->string('sigle', 50)->nullable();
            $table->string('forme_juridique', 30)->nullable();
            $table->string('niu', 15)->nullable()->comment('Numéro Identification Unique');
            $table->string('rccm', 30)->nullable()->comment('RCCM Congo');
            $table->enum('regime_fiscal', ['REEL_NORMAL', 'REEL_SIMPLIFIE', 'FORFAITAIRE', 'EXONERE'])->nullable();
            $table->text('adresse')->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('boite_postale', 20)->nullable();
            $table->string('pays', 3)->default('COG');
            $table->string('telephone', 20)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('site_web', 300)->nullable();
            $table->uuid('categorie_id')->nullable();
            $table->enum('type_fournisseur', ['LOCAL', 'CEMAC', 'INTERNATIONAL'])->default('LOCAL');
            $table->string('conditions_paiement', 30)->nullable();
            $table->string('devise_defaut', 3)->default('XAF');
            $table->decimal('taux_tva', 5, 2)->default(18.00);
            $table->boolean('assujetti_tva')->default(true);
            $table->enum('statut', ['PROSPECT', 'EN_VALIDATION', 'ACTIF', 'SUSPENDU', 'BLOQUE', 'INACTIF'])->default('PROSPECT');
            $table->decimal('note_evaluation', 3, 2)->nullable();
            $table->text('commentaire_interne')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by')->nullable();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->foreign('categorie_id')->references('id')->on('categories_fournisseur')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['societe_id', 'code']);
            $table->index('statut');
        });

        // Contacts fournisseur
        Schema::create('contacts_fournisseur', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fournisseur_id');
            $table->string('civilite', 10)->nullable();
            $table->string('nom', 100);
            $table->string('prenom', 100)->nullable();
            $table->string('fonction', 100)->nullable();
            $table->string('service', 100)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('telephone', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->boolean('est_principal')->default(false);
            $table->enum('type_contact', ['COMMERCIAL', 'TECHNIQUE', 'ADMINISTRATIF', 'COMPTABILITE', 'DIRECTION'])->nullable();
            $table->text('commentaire')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->foreign('fournisseur_id')->references('id')->on('fournisseurs')->onDelete('cascade');
        });

        // RIB fournisseur
        Schema::create('ribs_fournisseur', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fournisseur_id');
            $table->string('libelle', 100)->nullable();
            $table->string('titulaire', 200)->nullable();
            $table->string('banque', 100);
            $table->string('code_banque', 5)->nullable();
            $table->string('code_guichet', 5)->nullable();
            $table->string('numero_compte', 20);
            $table->string('cle_rib', 2)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('swift_bic', 11)->nullable();
            $table->string('devise', 3)->default('XAF');
            $table->boolean('est_principal')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->foreign('fournisseur_id')->references('id')->on('fournisseurs')->onDelete('cascade');
        });

        // Documents fournisseur
        Schema::create('documents_fournisseur', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fournisseur_id');
            $table->enum('type_document', [
                'RCCM', 'NIU', 'PATENTE', 'CNSS', 'ATT_FISCALE', 
                'RIB', 'ASSURANCE_RC', 'AGREMENT', 'CERTIFICATION', 'CONTRAT', 'AUTRE'
            ]);
            $table->string('libelle', 200)->nullable();
            $table->string('numero_document', 50)->nullable();
            $table->date('date_emission')->nullable();
            $table->date('date_expiration')->nullable();
            $table->string('organisme_emetteur', 100)->nullable();
            $table->string('fichier_nom', 255)->nullable();
            $table->string('fichier_url', 500)->nullable();
            $table->integer('fichier_taille')->nullable();
            $table->string('fichier_mime', 100)->nullable();
            $table->enum('statut', ['VALIDE', 'EXPIRE', 'A_RENOUVELER', 'EN_ATTENTE'])->default('EN_ATTENTE');
            $table->boolean('alerte_envoyee')->default(false);
            $table->text('commentaire')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('fournisseur_id')->references('id')->on('fournisseurs')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->index('statut');
            $table->index('date_expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_fournisseur');
        Schema::dropIfExists('ribs_fournisseur');
        Schema::dropIfExists('contacts_fournisseur');
        Schema::dropIfExists('fournisseurs');
        Schema::dropIfExists('categories_fournisseur');
    }
};
