<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LigneBonCommande extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lignes_bon_commande';

    protected $fillable = [
        'bon_commande_id',
        'ligne_demande_id',
        'numero_ligne',
        'designation',
        'description',
        'quantite',
        'unite_mesure_id',
        'prix_unitaire_xaf',
        'prix_unitaire_devise',
        'montant_xaf',
        'montant_devise',
        'quantite_recue',
        'quantite_facturee',
        'nature_depense_id',
        'centre_cout_id',
        'ligne_budgetaire_id',
        'statut_ligne',
        'commentaire',
    ];

    protected $casts = [
        'quantite' => 'decimal:3',
        'prix_unitaire_xaf' => 'decimal:0',
        'prix_unitaire_devise' => 'decimal:2',
        'montant_xaf' => 'decimal:0',
        'montant_devise' => 'decimal:2',
        'quantite_recue' => 'decimal:3',
        'quantite_facturee' => 'decimal:3',
    ];

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class);
    }

    public function uniteMesure(): BelongsTo
    {
        return $this->belongsTo(UniteMesure::class);
    }

    public function lignesReception(): HasMany
    {
        return $this->hasMany(LigneReception::class);
    }

    public function getQuantiteRestanteAttribute(): float
    {
        return max(0, $this->quantite - $this->quantite_recue);
    }

    public function isComplete(): bool
    {
        return $this->quantite_recue >= $this->quantite;
    }
}
