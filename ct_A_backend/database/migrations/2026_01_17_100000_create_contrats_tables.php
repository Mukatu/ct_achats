<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Types de contrats
        Schema::create('types_contrat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('libelle', 100);
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Contrats
        Schema::create('contrats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('societe_id')->constrained('societes');
            $table->string('numero', 50)->unique();
            $table->string('reference_externe', 100)->nullable()->comment('Numéro de contrat fournisseur');
            $table->foreignUuid('type_contrat_id')->constrained('types_contrat');
            $table->foreignUuid('fournisseur_id')->constrained('fournisseurs');

            // Localisation organisationnelle
            $table->foreignUuid('zone_id')->nullable()->constrained('zones');
            $table->foreignUuid('direction_id')->constrained('directions');
            $table->foreignUuid('service_id')->nullable()->constrained('services');

            // Informations du contrat
            $table->string('objet', 500);
            $table->text('description')->nullable();
            $table->date('date_signature');
            $table->date('date_debut');
            $table->date('date_fin')->nullable()->comment('Null si contrat à durée indéterminée');
            $table->boolean('reconduction_tacite')->default(false);
            $table->integer('preavis_jours')->nullable()->comment('Délai de préavis en jours');

            // Montants
            $table->string('periodicite', 20)->default('MENSUEL');
            $table->decimal('montant_periodique', 15, 0)->comment('Montant par période en XAF');
            $table->decimal('montant_annuel', 15, 0)->nullable()->comment('Montant annuel estimé');
            $table->decimal('taux_tva', 5, 2)->default(19.25);
            $table->boolean('tva_incluse')->default(false);

            // Paiement
            $table->string('conditions_paiement', 255)->nullable();
            $table->integer('jour_facturation')->nullable()->comment('Jour du mois pour facturation');

            // Responsables
            $table->foreignUuid('responsable_id')->nullable()->constrained('users')->comment('Responsable interne du contrat');
            $table->string('contact_fournisseur', 255)->nullable();

            // Statut et suivi
            $table->string('statut', 20)->default('BROUILLON');
            $table->text('commentaire')->nullable();

            // Documents
            $table->string('fichier_contrat')->nullable()->comment('Chemin vers le fichier PDF');

            // Audit
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->foreignUuid('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['societe_id', 'statut']);
            $table->index(['fournisseur_id']);
            $table->index(['date_fin']);
        });

        // Échéances / Factures liées aux contrats
        Schema::create('echeances_contrat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contrat_id')->constrained('contrats')->onDelete('cascade');
            $table->string('numero', 50)->comment('Numéro de l\'échéance (ex: CTR-2026-001/01)');
            $table->string('periode', 20)->comment('Période concernée (ex: 2026-01, 2026-Q1)');
            $table->date('date_echeance')->comment('Date prévue de facturation');
            $table->date('date_facture')->nullable()->comment('Date réelle de la facture');
            $table->string('numero_facture', 100)->nullable()->comment('Numéro de facture fournisseur');

            // Montants
            $table->decimal('montant_prevu', 15, 0)->comment('Montant prévu selon contrat');
            $table->decimal('montant_facture', 15, 0)->nullable()->comment('Montant réellement facturé');
            $table->decimal('montant_ht', 15, 0)->nullable();
            $table->decimal('montant_tva', 15, 0)->nullable();
            $table->decimal('montant_ttc', 15, 0)->nullable();

            // Paiement
            $table->string('statut', 20)->default('A_VENIR');
            $table->date('date_paiement')->nullable();
            $table->string('reference_paiement', 100)->nullable();

            // Lien optionnel vers BC (si on génère un BC pour cette échéance)
            $table->foreignUuid('bon_commande_id')->nullable()->constrained('bons_commande');

            // Commentaires
            $table->text('commentaire')->nullable();

            // Audit
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->foreignUuid('validated_by')->nullable()->constrained('users');
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index(['contrat_id', 'statut']);
            $table->index(['date_echeance']);
            $table->unique(['contrat_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('echeances_contrat');
        Schema::dropIfExists('contrats');
        Schema::dropIfExists('types_contrat');
    }
};
