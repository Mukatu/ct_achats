<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criteres_ponderation', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('code')->unique(); // PRIX, DELAI, PAIEMENT, GARANTIE, TECHNIQUE
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->integer('poids')->default(0); // Pourcentage (0-100)
            $table->boolean('actif')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
        });

        // Insérer les critères par défaut
        $this->insertDefaultCriteres();
    }

    public function down(): void
    {
        Schema::dropIfExists('criteres_ponderation');
    }

    private function insertDefaultCriteres(): void
    {
        $societe = DB::table('societes')->first();
        if (!$societe) return;

        $criteres = [
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'societe_id' => $societe->id,
                'code' => 'PRIX',
                'libelle' => 'Prix',
                'description' => 'Prix unitaire proposé par le fournisseur',
                'poids' => 40,
                'ordre' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'societe_id' => $societe->id,
                'code' => 'DELAI',
                'libelle' => 'Délai de livraison',
                'description' => 'Délai de livraison en jours',
                'poids' => 25,
                'ordre' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'societe_id' => $societe->id,
                'code' => 'PAIEMENT',
                'libelle' => 'Conditions de paiement',
                'description' => 'Délai de paiement accordé en jours',
                'poids' => 15,
                'ordre' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'societe_id' => $societe->id,
                'code' => 'GARANTIE',
                'libelle' => 'Garantie',
                'description' => 'Durée de garantie en mois',
                'poids' => 10,
                'ordre' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'societe_id' => $societe->id,
                'code' => 'TECHNIQUE',
                'libelle' => 'Offre technique',
                'description' => 'Conformité technique de l\'offre',
                'poids' => 10,
                'ordre' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('criteres_ponderation')->insert($criteres);
    }
};
