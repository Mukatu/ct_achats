<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration corrective : la migration 2026_01_12_102935 utilisait Schema::create
 * sur une table déjà créée par 2024_01_01_000030, causant un échec silencieux sur prod.
 * Cette migration ajoute les colonnes manquantes de façon idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Corriger lignes_demande_achat si les colonnes du nouveau schéma sont absentes
        Schema::table('lignes_demande_achat', function (Blueprint $table) {
            // Renommer prix_unitaire -> prix_unitaire_estime si nécessaire
            if (
                Schema::hasColumn('lignes_demande_achat', 'prix_unitaire') &&
                !Schema::hasColumn('lignes_demande_achat', 'prix_unitaire_estime')
            ) {
                $table->renameColumn('prix_unitaire', 'prix_unitaire_estime');
            } elseif (!Schema::hasColumn('lignes_demande_achat', 'prix_unitaire_estime')) {
                $table->decimal('prix_unitaire_estime', 15, 0)->nullable()->after('unite_mesure_id');
            }

            // Renommer montant -> montant_estime si nécessaire
            if (
                Schema::hasColumn('lignes_demande_achat', 'montant') &&
                !Schema::hasColumn('lignes_demande_achat', 'montant_estime')
            ) {
                $table->renameColumn('montant', 'montant_estime');
            } elseif (!Schema::hasColumn('lignes_demande_achat', 'montant_estime')) {
                $table->decimal('montant_estime', 15, 0)->nullable()->after('prix_unitaire_estime');
            }

            if (!Schema::hasColumn('lignes_demande_achat', 'offre_selectionnee_id')) {
                $table->uuid('offre_selectionnee_id')->nullable();
            }

            if (!Schema::hasColumn('lignes_demande_achat', 'deleted_at')) {
                $table->softDeletes();
            }

            if (!Schema::hasColumn('lignes_demande_achat', 'created_by')) {
                $table->uuid('created_by')->nullable();
            }

            if (!Schema::hasColumn('lignes_demande_achat', 'updated_by')) {
                $table->uuid('updated_by')->nullable();
            }
        });

        // Créer offres_fournisseur si absente (migration 2026_01_12_103118 a pu échouer)
        if (!Schema::hasTable('offres_fournisseur')) {
            Schema::create('offres_fournisseur', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('ligne_demande_achat_id');
                $table->uuid('fournisseur_id');
                $table->decimal('prix_unitaire', 15, 0);
                $table->integer('delai_livraison_jours');
                $table->integer('conditions_paiement_jours');
                $table->string('garantie')->nullable();
                $table->integer('garantie_mois')->nullable();
                $table->enum('offre_technique', ['CONFORME', 'NON_CONFORME'])->default('CONFORME');
                $table->text('commentaire')->nullable();
                $table->string('reference_offre')->nullable();
                $table->date('date_offre')->nullable();
                $table->date('date_validite')->nullable();
                $table->decimal('score', 5, 2)->nullable();
                $table->boolean('est_selectionnee')->default(false);
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('ligne_demande_achat_id')->references('id')->on('lignes_demande_achat')->onDelete('cascade');
                $table->foreign('fournisseur_id')->references('id')->on('fournisseurs')->onDelete('cascade');
                $table->unique(['ligne_demande_achat_id', 'fournisseur_id'], 'offre_ligne_fournisseur_unique');
            });
        }

        // Ajouter la clé étrangère offre_selectionnee_id -> offres_fournisseur si absente
        try {
            Schema::table('lignes_demande_achat', function (Blueprint $table) {
                $table->foreign('offre_selectionnee_id')
                    ->references('id')
                    ->on('offres_fournisseur')
                    ->nullOnDelete();
            });
        } catch (\Throwable $e) {
            // La contrainte existe déjà ou la colonne source vient d'être renommée
        }

        // Créer criteres_ponderation si absente (migration 2026_01_12_103120)
        if (!Schema::hasTable('criteres_ponderation')) {
            Schema::create('criteres_ponderation', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('societe_id');
                $table->string('code', 20);
                $table->string('libelle', 100);
                $table->decimal('poids', 5, 2)->default(0);
                $table->boolean('actif')->default(true);
                $table->timestamps();

                $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
                $table->unique(['societe_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        // Non réversible de façon sûre - les colonnes renommées ne peuvent pas être
        // restaurées sans risque de perte de données
    }
};
