<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Réceptions
        Schema::create('receptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('numero', 25)->comment('Format: BR-NNNN/AA');
            $table->uuid('bon_commande_id');
            $table->date('date_reception');
            $table->uuid('receptionnaire_id');
            $table->string('lieu_reception', 200)->nullable();
            $table->enum('type_reception', ['LIVRAISON', 'SERVICE_FAIT', 'PARTIELLE'])->default('LIVRAISON');
            $table->string('numero_bl_fournisseur', 50)->nullable();
            $table->date('date_bl_fournisseur')->nullable();
            $table->string('numero_tracking', 100)->nullable();
            $table->string('transporteur', 100)->nullable();
            $table->enum('statut', ['BROUILLON', 'VALIDEE', 'EN_LITIGE', 'ANNULEE'])->default('BROUILLON');
            $table->text('commentaire')->nullable();
            $table->string('fichier_bl_url', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by');

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->foreign('bon_commande_id')->references('id')->on('bons_commande');
            $table->foreign('receptionnaire_id')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');
            
            $table->unique(['societe_id', 'numero']);
            $table->index('statut');
        });

        // Lignes de réception
        Schema::create('lignes_reception', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reception_id');
            $table->uuid('ligne_bon_commande_id');
            $table->integer('numero_ligne');
            $table->decimal('quantite_attendue', 15, 3);
            $table->decimal('quantite_recue', 15, 3);
            $table->decimal('quantite_conforme', 15, 3)->default(0);
            $table->decimal('quantite_non_conforme', 15, 3)->default(0);
            $table->decimal('quantite_refusee', 15, 3)->default(0);
            $table->text('motif_non_conformite')->nullable();
            $table->text('motif_refus')->nullable();
            $table->string('numero_lot', 50)->nullable();
            $table->date('date_peremption')->nullable();
            $table->string('numero_serie', 100)->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->foreign('reception_id')->references('id')->on('receptions')->onDelete('cascade');
            $table->foreign('ligne_bon_commande_id')->references('id')->on('lignes_bon_commande');
            
            $table->unique(['reception_id', 'numero_ligne']);
        });

        // Factures
        Schema::create('factures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('numero_interne', 30)->comment('FACT-NNNNNN/AA');
            $table->string('numero_fournisseur', 50);
            $table->uuid('fournisseur_id');
            $table->uuid('bon_commande_id')->nullable();
            $table->enum('type_facture', ['FACTURE', 'AVOIR', 'ACOMPTE', 'SITUATION'])->default('FACTURE');
            $table->date('date_facture');
            $table->date('date_reception');
            $table->date('date_echeance');
            
            // Montants
            $table->decimal('montant_ht', 18, 0)->comment('XAF');
            $table->decimal('taux_tva', 5, 2)->default(18.00);
            $table->decimal('montant_tva', 18, 0)->comment('XAF');
            $table->decimal('montant_ttc', 18, 0)->comment('XAF');
            $table->decimal('retenue_source', 18, 0)->nullable()->comment('XAF');
            $table->decimal('net_a_payer', 18, 0)->comment('XAF');
            $table->string('devise', 3)->default('XAF');
            
            // Statuts
            $table->enum('statut', [
                'BROUILLON', 'A_RAPPROCHER', 'EN_RAPPROCHEMENT', 'RAPPROCHEE',
                'A_VALIDER', 'VALIDEE', 'EN_LITIGE', 'REJETEE', 'ANNULEE'
            ])->default('BROUILLON');
            $table->enum('statut_paiement', [
                'NON_PAYEE', 'PARTIEL', 'EN_PAIEMENT', 'PAYEE', 'SUSPENDUE'
            ])->default('NON_PAYEE');
            
            $table->timestamp('date_validation')->nullable();
            $table->uuid('valideur_id')->nullable();
            $table->timestamp('date_paiement')->nullable();
            $table->string('reference_paiement', 100)->nullable();
            $table->decimal('montant_paye', 18, 0)->nullable();
            $table->decimal('ecart_rapprochement', 18, 0)->nullable();
            $table->text('motif_ecart')->nullable();
            $table->string('fichier_facture_url', 500)->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by');
            $table->uuid('updated_by')->nullable();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->foreign('fournisseur_id')->references('id')->on('fournisseurs');
            $table->foreign('bon_commande_id')->references('id')->on('bons_commande')->nullOnDelete();
            $table->foreign('valideur_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            
            $table->unique(['societe_id', 'numero_interne']);
            $table->unique(['societe_id', 'fournisseur_id', 'numero_fournisseur'], 'facture_fournisseur_unique');
            $table->index('statut');
            $table->index('statut_paiement');
            $table->index('date_echeance');
        });

        // Lignes de facture
        Schema::create('lignes_facture', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('facture_id');
            $table->integer('numero_ligne');
            $table->uuid('ligne_bon_commande_id')->nullable();
            $table->uuid('ligne_reception_id')->nullable();
            $table->string('reference_fournisseur', 50)->nullable();
            $table->string('designation', 500);
            $table->decimal('quantite', 15, 3);
            $table->uuid('unite_mesure_id')->nullable();
            $table->decimal('prix_unitaire_ht', 18, 0);
            $table->decimal('remise_percent', 5, 2)->nullable();
            $table->decimal('montant_ht', 18, 0);
            $table->decimal('taux_tva', 5, 2)->default(18.00);
            $table->decimal('montant_tva', 18, 0);
            $table->decimal('montant_ttc', 18, 0);
            $table->uuid('nature_depense_id')->nullable();
            $table->uuid('centre_cout_id')->nullable();
            $table->uuid('ligne_budgetaire_id')->nullable();
            $table->decimal('ecart_prix', 18, 0)->nullable();
            $table->decimal('ecart_quantite', 15, 3)->nullable();
            $table->decimal('ecart_montant', 18, 0)->nullable();
            $table->enum('statut_rapprochement', [
                'NON_RAPPROCHEE', 'RAPPROCHEE_OK', 'ECART_PRIX', 
                'ECART_QUANTITE', 'ECART_MULTIPLE', 'FORCE', 'LITIGE'
            ])->default('NON_RAPPROCHEE');
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->foreign('facture_id')->references('id')->on('factures')->onDelete('cascade');
            $table->foreign('ligne_bon_commande_id')->references('id')->on('lignes_bon_commande')->nullOnDelete();
            $table->foreign('ligne_reception_id')->references('id')->on('lignes_reception')->nullOnDelete();
            $table->foreign('unite_mesure_id')->references('id')->on('unites_mesure')->nullOnDelete();
            $table->foreign('nature_depense_id')->references('id')->on('natures_depense')->nullOnDelete();
            $table->foreign('centre_cout_id')->references('id')->on('centres_cout')->nullOnDelete();
            $table->foreign('ligne_budgetaire_id')->references('id')->on('lignes_budgetaires')->nullOnDelete();
            
            $table->unique(['facture_id', 'numero_ligne']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_facture');
        Schema::dropIfExists('factures');
        Schema::dropIfExists('lignes_reception');
        Schema::dropIfExists('receptions');
    }
};
