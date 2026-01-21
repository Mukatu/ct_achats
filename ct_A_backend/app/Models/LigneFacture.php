<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneFacture extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lignes_facture';

    protected $fillable = [
        'facture_id',
        'numero_ligne',
        'ligne_bon_commande_id',
        'ligne_reception_id',
        'reference_fournisseur',
        'designation',
        'quantite',
        'unite_mesure_id',
        'prix_unitaire_ht',
        'remise_percent',
        'montant_ht',
        'taux_tva',
        'montant_tva',
        'montant_ttc',
        'nature_depense_id',
        'centre_cout_id',
        'ligne_budgetaire_id',
        'ecart_prix',
        'ecart_quantite',
        'ecart_montant',
        'statut_rapprochement',
        'commentaire',
    ];

    protected $casts = [
        'quantite' => 'decimal:3',
        'prix_unitaire_ht' => 'decimal:0',
        'remise_percent' => 'decimal:2',
        'montant_ht' => 'decimal:0',
        'taux_tva' => 'decimal:2',
        'montant_tva' => 'decimal:0',
        'montant_ttc' => 'decimal:0',
        'ecart_prix' => 'decimal:0',
        'ecart_quantite' => 'decimal:3',
        'ecart_montant' => 'decimal:0',
    ];

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function ligneBonCommande(): BelongsTo
    {
        return $this->belongsTo(LigneBonCommande::class);
    }

    public function ligneReception(): BelongsTo
    {
        return $this->belongsTo(LigneReception::class);
    }

    public function uniteMesure(): BelongsTo
    {
        return $this->belongsTo(UniteMesure::class);
    }

    // Verifier si la ligne a des ecarts
    public function hasEcart(): bool
    {
        return $this->ecart_prix != 0 || $this->ecart_quantite != 0 || $this->ecart_montant != 0;
    }

    // Calculer le montant avec remise
    public function calculerMontant(): float
    {
        $montantBrut = $this->quantite * $this->prix_unitaire_ht;
        if ($this->remise_percent && $this->remise_percent > 0) {
            $montantBrut = $montantBrut * (1 - $this->remise_percent / 100);
        }
        return $montantBrut;
    }
}
