<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unités de mesure
        Schema::create('unites_mesure', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique();
            $table->string('libelle', 50);
            $table->string('symbole', 10);
            $table->enum('type', ['QUANTITE', 'POIDS', 'LONGUEUR', 'SURFACE', 'VOLUME', 'TEMPS'])->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Expression de Besoins (EB)
        Schema::create('expressions_besoin', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('numero', 25)->comment('Format: NNNN/AA - EB');
            $table->date('date_expression');
            $table->uuid('zone_id');
            $table->uuid('direction_id');
            $table->uuid('service_id')->nullable();
            $table->uuid('demandeur_id');
            $table->string('objet', 500);
            $table->text('description_detaillee')->nullable();
            $table->text('quantite_souhaitee')->nullable();
            $table->date('date_besoin')->nullable();
            $table->decimal('estimation', 18, 0)->nullable()->comment('XAF');
            $table->uuid('acheteur_id')->nullable();
            $table->uuid('fournisseur_suggere_id')->nullable();
            $table->enum('statut', [
                'EN_SUSPENS', 'EN_COURS_ACH', 'EN_COURS_CDG', 'EN_COURS_DFC', 
                'EN_COURS_DG', 'TRAITE', 'ANNULE', 'NA'
            ])->default('EN_SUSPENS');
            $table->timestamp('date_validation')->nullable();
            $table->text('motif_rejet')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by');
            $table->uuid('updated_by')->nullable();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->foreign('zone_id')->references('id')->on('zones');
            $table->foreign('direction_id')->references('id')->on('directions');
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->foreign('demandeur_id')->references('id')->on('users');
            $table->foreign('acheteur_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('fournisseur_suggere_id')->references('id')->on('fournisseurs')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            
            $table->unique(['societe_id', 'numero']);
            $table->index('statut');
            $table->index('date_expression');
        });

        // Demande d'Achat (DA / DAC)
        Schema::create('demandes_achat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('numero', 25)->comment('Format: NNNN/AA - DA ou DAC');
            $table->enum('type_demande', ['DA', 'DAC'])->default('DA');
            $table->date('date_demande');
            $table->uuid('expression_besoin_id')->nullable();
            $table->uuid('zone_id');
            $table->uuid('direction_id');
            $table->uuid('service_id')->nullable();
            $table->uuid('demandeur_id');
            $table->string('objet', 500);
            $table->text('description')->nullable();
            $table->uuid('acheteur_id')->nullable();
            $table->decimal('montant', 18, 0)->default(0)->comment('XAF');
            $table->enum('statut', [
                'EN_SUSPENS', 'EN_COURS_ACH', 'EN_COURS_CDG', 'EN_COURS_DFC', 
                'EN_COURS_DG', 'TRAITE', 'ANNULE', 'NA'
            ])->default('EN_COURS_ACH');
            $table->timestamp('date_validation')->nullable();
            $table->text('motif_rejet')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by');
            $table->uuid('updated_by')->nullable();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->foreign('expression_besoin_id')->references('id')->on('expressions_besoin')->nullOnDelete();
            $table->foreign('zone_id')->references('id')->on('zones');
            $table->foreign('direction_id')->references('id')->on('directions');
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->foreign('demandeur_id')->references('id')->on('users');
            $table->foreign('acheteur_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            
            $table->unique(['societe_id', 'numero']);
            $table->index('statut');
            $table->index('type_demande');
        });

        // Lignes de demande d'achat
        Schema::create('lignes_demande_achat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('demande_achat_id');
            $table->integer('numero_ligne');
            $table->string('designation', 500);
            $table->text('description')->nullable();
            $table->decimal('quantite', 15, 3);
            $table->uuid('unite_mesure_id')->nullable();
            $table->decimal('prix_unitaire', 18, 0)->default(0)->comment('XAF');
            $table->decimal('montant', 18, 0)->default(0)->comment('XAF');
            $table->uuid('nature_depense_id')->nullable();
            $table->uuid('centre_cout_id')->nullable();
            $table->uuid('ligne_budgetaire_id')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->foreign('demande_achat_id')->references('id')->on('demandes_achat')->onDelete('cascade');
            $table->foreign('unite_mesure_id')->references('id')->on('unites_mesure')->nullOnDelete();
            $table->foreign('nature_depense_id')->references('id')->on('natures_depense')->nullOnDelete();
            $table->foreign('centre_cout_id')->references('id')->on('centres_cout')->nullOnDelete();
            $table->foreign('ligne_budgetaire_id')->references('id')->on('lignes_budgetaires')->nullOnDelete();
            
            $table->unique(['demande_achat_id', 'numero_ligne']);
        });

        // Bon de Commande (BC)
        Schema::create('bons_commande', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('numero', 20)->comment('Format: NNN/AA');
            $table->enum('type_bc', ['BCAL', 'BCL', 'BCAI', 'BCI', 'IPO'])->default('BCAL');
            $table->date('date_bc');
            $table->uuid('demande_achat_id')->nullable();
            $table->uuid('expression_besoin_id')->nullable();
            $table->uuid('fournisseur_id');
            $table->uuid('contact_fournisseur_id')->nullable();
            $table->uuid('zone_id');
            $table->uuid('direction_id');
            $table->uuid('demandeur_id');
            $table->uuid('acheteur_id')->nullable();
            $table->string('objet', 500);
            $table->text('nature_prestation')->nullable();
            
            // Montants en XAF
            $table->decimal('montant_ht_xaf', 18, 0)->default(0);
            $table->decimal('taux_tva', 5, 2)->default(18.00);
            $table->decimal('montant_tva', 18, 0)->default(0);
            $table->decimal('montant_ttc_xaf', 18, 0)->default(0);
            
            // Montants en devise étrangère (pour BCAI/BCI/IPO)
            $table->string('devise_etrangere', 3)->nullable();
            $table->decimal('montant_devise', 15, 2)->nullable();
            $table->decimal('taux_change', 10, 4)->nullable();
            
            // Conditions
            $table->string('conditions_paiement', 50)->nullable();
            $table->string('conditions_livraison', 100)->nullable();
            $table->text('adresse_livraison')->nullable();
            $table->string('personne_contact', 100)->nullable();
            $table->string('telephone_contact', 20)->nullable();
            $table->date('date_livraison_prevue')->nullable();
            $table->string('numero_devis', 50)->nullable();
            
            // Statut
            $table->enum('statut', [
                'NC', 'EN_COURS_A', 'EN_COURS_CDG', 'EN_COURS_DFC',
                'DAC_CDG', 'DAC_DG', 'DAC_TRESO',
                'EN_COURS_FSSEUR', 'LIVRAISON_PARTIELLE', 'LIVRE', 'TRAITE', 'ANNULE'
            ])->default('NC');
            
            $table->timestamp('date_envoi_fournisseur')->nullable();
            $table->date('date_accuse_reception')->nullable();
            $table->string('fichier_bc_url', 500)->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by');
            $table->uuid('updated_by')->nullable();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->foreign('demande_achat_id')->references('id')->on('demandes_achat')->nullOnDelete();
            $table->foreign('expression_besoin_id')->references('id')->on('expressions_besoin')->nullOnDelete();
            $table->foreign('fournisseur_id')->references('id')->on('fournisseurs');
            $table->foreign('contact_fournisseur_id')->references('id')->on('contacts_fournisseur')->nullOnDelete();
            $table->foreign('zone_id')->references('id')->on('zones');
            $table->foreign('direction_id')->references('id')->on('directions');
            $table->foreign('demandeur_id')->references('id')->on('users');
            $table->foreign('acheteur_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            
            $table->unique(['societe_id', 'numero']);
            $table->index('statut');
            $table->index('type_bc');
            $table->index('date_bc');
        });

        // Lignes de bon de commande
        Schema::create('lignes_bon_commande', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bon_commande_id');
            $table->uuid('ligne_demande_id')->nullable();
            $table->integer('numero_ligne');
            $table->string('designation', 500);
            $table->text('description')->nullable();
            $table->decimal('quantite', 15, 3);
            $table->uuid('unite_mesure_id')->nullable();
            $table->decimal('prix_unitaire_xaf', 18, 0)->default(0);
            $table->decimal('prix_unitaire_devise', 15, 2)->nullable();
            $table->decimal('montant_xaf', 18, 0)->default(0);
            $table->decimal('montant_devise', 15, 2)->nullable();
            $table->decimal('quantite_recue', 15, 3)->default(0);
            $table->decimal('quantite_facturee', 15, 3)->default(0);
            $table->uuid('nature_depense_id')->nullable();
            $table->uuid('centre_cout_id')->nullable();
            $table->uuid('ligne_budgetaire_id')->nullable();
            $table->enum('statut_ligne', ['EN_ATTENTE', 'EN_COURS', 'RECU_PARTIEL', 'RECU_TOTAL', 'SOLDE', 'ANNULE'])->default('EN_ATTENTE');
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->foreign('bon_commande_id')->references('id')->on('bons_commande')->onDelete('cascade');
            $table->foreign('ligne_demande_id')->references('id')->on('lignes_demande_achat')->nullOnDelete();
            $table->foreign('unite_mesure_id')->references('id')->on('unites_mesure')->nullOnDelete();
            $table->foreign('nature_depense_id')->references('id')->on('natures_depense')->nullOnDelete();
            $table->foreign('centre_cout_id')->references('id')->on('centres_cout')->nullOnDelete();
            $table->foreign('ligne_budgetaire_id')->references('id')->on('lignes_budgetaires')->nullOnDelete();
            
            $table->unique(['bon_commande_id', 'numero_ligne']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_bon_commande');
        Schema::dropIfExists('bons_commande');
        Schema::dropIfExists('lignes_demande_achat');
        Schema::dropIfExists('demandes_achat');
        Schema::dropIfExists('expressions_besoin');
        Schema::dropIfExists('unites_mesure');
    }
};
