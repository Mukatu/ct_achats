<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les champs pour la gestion des pièces justificatives
     * des Demandes d'Achat Caisse (DAC).
     * Les DAC n'aboutissent pas à des BC mais sont des engagements directs
     * payés à la caisse avec une pièce justificative.
     */
    public function up(): void
    {
        Schema::table('demandes_achat', function (Blueprint $table) {
            // Date du paiement effectif à la caisse
            $table->date('date_paiement_caisse')->nullable()->after('date_validation');

            // Référence du paiement (numéro de reçu, bordereau, etc.)
            $table->string('reference_paiement', 100)->nullable()->after('date_paiement_caisse');

            // Montant réellement payé (peut différer du montant estimé)
            $table->decimal('montant_paye', 18, 2)->nullable()->after('reference_paiement');

            // Chemin vers le fichier justificatif (reçu, facture, etc.)
            $table->string('piece_justificative')->nullable()->after('montant_paye');

            // Observations lors de la clôture
            $table->text('observations_cloture')->nullable()->after('piece_justificative');

            // Utilisateur ayant clôturé la DAC
            $table->uuid('cloture_par')->nullable()->after('observations_cloture');
            $table->foreign('cloture_par')->references('id')->on('users')->nullOnDelete();

            // Date de clôture
            $table->timestamp('date_cloture')->nullable()->after('cloture_par');
        });
    }

    public function down(): void
    {
        Schema::table('demandes_achat', function (Blueprint $table) {
            $table->dropForeign(['cloture_par']);
            $table->dropColumn([
                'date_paiement_caisse',
                'reference_paiement',
                'montant_paye',
                'piece_justificative',
                'observations_cloture',
                'cloture_par',
                'date_cloture',
            ]);
        });
    }
};
