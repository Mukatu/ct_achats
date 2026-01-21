<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneReception extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lignes_reception';

    protected $fillable = [
        'reception_id',
        'ligne_bon_commande_id',
        'numero_ligne',
        'quantite_attendue',
        'quantite_recue',
        'quantite_conforme',
        'quantite_non_conforme',
        'quantite_refusee',
        'motif_non_conformite',
        'motif_refus',
        'numero_lot',
        'date_peremption',
        'numero_serie',
        'commentaire',
    ];

    protected $casts = [
        'quantite_attendue' => 'decimal:3',
        'quantite_recue' => 'decimal:3',
        'quantite_conforme' => 'decimal:3',
        'quantite_non_conforme' => 'decimal:3',
        'quantite_refusee' => 'decimal:3',
        'date_peremption' => 'date',
    ];

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    public function ligneBonCommande(): BelongsTo
    {
        return $this->belongsTo(LigneBonCommande::class);
    }

    // Vérifie si la ligne est complète
    public function isComplete(): bool
    {
        return $this->quantite_recue >= $this->quantite_attendue;
    }

    // Vérifie si la ligne est conforme
    public function isConforme(): bool
    {
        return $this->quantite_non_conforme == 0 && $this->quantite_refusee == 0;
    }

    // Calcule le taux de conformité
    public function getTauxConformite(): float
    {
        if ($this->quantite_recue == 0) return 0;
        return round(($this->quantite_conforme / $this->quantite_recue) * 100, 2);
    }
}
