<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Circuits de validation
        Schema::create('circuits_validation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('code', 30);
            $table->string('libelle', 100);
            $table->text('description')->nullable();
            $table->enum('type_document', ['EXPRESSION_BESOIN', 'DEMANDE_ACHAT', 'BON_COMMANDE', 'FACTURE', 'FOURNISSEUR']);
            $table->boolean('est_defaut')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->unique(['societe_id', 'code']);
        });

        // Étapes de circuit
        Schema::create('etapes_circuit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('circuit_id');
            $table->integer('ordre');
            $table->string('libelle', 100);
            $table->text('description')->nullable();
            $table->enum('type_valideur', [
                'UTILISATEUR', 'ROLE', 'HIERARCHIE', 'HIERARCHIE_N2',
                'RESPONSABLE_SERVICE', 'RESPONSABLE_DIRECTION', 'RESPONSABLE_BUDGET'
            ])->default('ROLE');
            $table->uuid('valideur_id')->nullable();
            $table->uuid('role_id')->nullable();
            $table->json('condition')->nullable()->comment('Condition DSL JSON');
            $table->enum('mode_validation', ['TOUS', 'UN_PARMI', 'MAJORITE'])->default('UN_PARMI');
            $table->integer('delai_traitement_jours')->nullable();
            $table->integer('delai_relance_jours')->nullable();
            $table->integer('delai_escalade_jours')->nullable();
            $table->uuid('escalade_vers_id')->nullable();
            $table->boolean('obligatoire')->default(true);
            $table->boolean('notification_email')->default(true);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->foreign('circuit_id')->references('id')->on('circuits_validation')->onDelete('cascade');
            $table->foreign('valideur_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('escalade_vers_id')->references('id')->on('users')->nullOnDelete();
            
            $table->unique(['circuit_id', 'ordre']);
        });

        // Règles d'affectation de circuit
        Schema::create('regles_affectation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->uuid('circuit_id');
            $table->integer('priorite')->default(100);
            $table->string('libelle', 100)->nullable();
            $table->json('condition')->comment('Condition DSL JSON');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->foreign('circuit_id')->references('id')->on('circuits_validation')->onDelete('cascade');
        });

        // Instances de validation
        Schema::create('instances_validation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('circuit_id');
            $table->string('document_type', 50);
            $table->uuid('document_id');
            $table->uuid('initiateur_id');
            $table->uuid('etape_courante_id')->nullable();
            $table->enum('statut', ['EN_COURS', 'APPROUVE', 'REFUSE', 'ANNULE'])->default('EN_COURS');
            $table->timestamp('date_cloture')->nullable();
            $table->text('commentaire_cloture')->nullable();
            $table->timestamps();

            $table->foreign('circuit_id')->references('id')->on('circuits_validation');
            $table->foreign('initiateur_id')->references('id')->on('users');
            $table->foreign('etape_courante_id')->references('id')->on('etapes_circuit')->nullOnDelete();
            
            $table->index(['document_type', 'document_id']);
            $table->index('statut');
        });

        // Décisions de validation
        Schema::create('decisions_validation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('instance_id');
            $table->uuid('etape_id');
            $table->uuid('valideur_id');
            $table->uuid('valideur_effectif_id')->nullable()->comment('Si délégation');
            $table->uuid('delegation_id')->nullable();
            $table->enum('decision', ['EN_ATTENTE', 'APPROUVE', 'REFUSE', 'RENVOYE', 'DELEGUE', 'ESCALADE'])->default('EN_ATTENTE');
            $table->timestamp('date_sollicitation');
            $table->timestamp('date_decision')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamp('date_relance')->nullable();
            $table->integer('nb_relances')->default(0);
            $table->timestamps();

            $table->foreign('instance_id')->references('id')->on('instances_validation')->onDelete('cascade');
            $table->foreign('etape_id')->references('id')->on('etapes_circuit');
            $table->foreign('valideur_id')->references('id')->on('users');
            $table->foreign('valideur_effectif_id')->references('id')->on('users')->nullOnDelete();
            
            $table->index(['valideur_id', 'decision']);
        });

        // Délégations
        Schema::create('delegations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('delegant_id');
            $table->uuid('delegataire_id');
            $table->timestamp('date_debut');
            $table->timestamp('date_fin');
            $table->string('motif', 500)->nullable();
            $table->json('perimetre')->nullable()->comment('Types docs, montants...');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->foreign('delegant_id')->references('id')->on('users');
            $table->foreign('delegataire_id')->references('id')->on('users');
            
            $table->index(['delegataire_id', 'actif']);
        });

        // Journal d'audit
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamp('timestamp')->useCurrent();
            $table->uuid('user_id')->nullable();
            $table->string('user_nom', 200)->nullable();
            $table->string('action', 50);
            $table->string('entite_type', 50);
            $table->uuid('entite_id');
            $table->string('entite_numero', 50)->nullable();
            $table->json('donnees_avant')->nullable();
            $table->json('donnees_apres')->nullable();
            $table->json('champs_modifies')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('session_id', 100)->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            
            $table->index('timestamp');
            $table->index(['entite_type', 'entite_id']);
            $table->index(['user_id', 'timestamp']);
            $table->index('action');
        });

        // Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('destinataire_id');
            $table->string('type', 50);
            $table->enum('priorite', ['BASSE', 'NORMALE', 'HAUTE', 'URGENTE'])->default('NORMALE');
            $table->string('titre', 200);
            $table->text('message');
            $table->string('document_type', 50)->nullable();
            $table->uuid('document_id')->nullable();
            $table->string('url_action', 500)->nullable();
            $table->enum('canal', ['APP', 'EMAIL', 'SMS', 'TOUS'])->default('APP');
            $table->boolean('lue')->default(false);
            $table->timestamp('date_lecture')->nullable();
            $table->boolean('envoyee')->default(false);
            $table->timestamp('date_envoi')->nullable();
            $table->text('erreur_envoi')->nullable();
            $table->timestamp('expire_at')->nullable();
            $table->timestamps();

            $table->foreign('destinataire_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['destinataire_id', 'lue', 'created_at']);
        });

        // Commentaires
        Schema::create('commentaires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_type', 50);
            $table->uuid('document_id');
            $table->uuid('auteur_id');
            $table->uuid('parent_id')->nullable();
            $table->text('contenu');
            $table->boolean('est_interne')->default(true);
            $table->boolean('modifie')->default(false);
            $table->timestamp('date_modification')->nullable();
            $table->boolean('supprime')->default(false);
            $table->timestamp('date_suppression')->nullable();
            $table->timestamps();

            $table->foreign('auteur_id')->references('id')->on('users');
            $table->foreign('parent_id')->references('id')->on('commentaires')->nullOnDelete();
            
            $table->index(['document_type', 'document_id', 'created_at']);
        });

        // Pièces jointes
        Schema::create('pieces_jointes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_type', 50);
            $table->uuid('document_id');
            $table->enum('type_piece', [
                'DEVIS', 'BON_COMMANDE', 'BON_LIVRAISON', 'FACTURE', 'AVOIR',
                'CONTRAT', 'CAHIER_CHARGES', 'SPECIFICATION', 'PV_RECEPTION',
                'PHOTO', 'CORRESPONDANCE', 'AUTRE'
            ])->default('AUTRE');
            $table->string('libelle', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('nom_fichier', 255);
            $table->string('nom_original', 255);
            $table->string('extension', 20)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('taille')->nullable();
            $table->string('storage_provider', 30)->default('local');
            $table->string('storage_path', 500);
            $table->string('checksum', 64)->nullable();
            $table->boolean('est_principal')->default(false);
            $table->integer('ordre')->nullable();
            $table->uuid('uploaded_by');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users');
            
            $table->index(['document_type', 'document_id']);
        });

        // Séquences de numérotation
        Schema::create('sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('type_document', 30);
            $table->year('annee');
            $table->integer('dernier_numero')->default(0);
            $table->string('prefixe', 20)->nullable();
            $table->string('suffixe', 20)->nullable();
            $table->integer('longueur_numero')->default(4);
            $table->timestamps();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->unique(['societe_id', 'type_document', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('pieces_jointes');
        Schema::dropIfExists('commentaires');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('decisions_validation');
        Schema::dropIfExists('instances_validation');
        Schema::dropIfExists('regles_affectation');
        Schema::dropIfExists('etapes_circuit');
        Schema::dropIfExists('circuits_validation');
    }
};
