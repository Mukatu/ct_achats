<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LigneDemandeAchat extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lignes_demande_achat';

    protected $fillable = [
        'demande_achat_id',
        'numero_ligne',
        'designation',
        'description',
        'quantite',
        'unite_mesure_id',
        'prix_unitaire_estime',
        'montant_estime',
        'offre_selectionnee_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantite' => 'decimal:2',
        'prix_unitaire_estime' => 'decimal:0',
        'montant_estime' => 'decimal:0',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function demandeAchat(): BelongsTo
    {
        return $this->belongsTo(DemandeAchat::class);
    }

    public function uniteMesure(): BelongsTo
    {
        return $this->belongsTo(UniteMesure::class);
    }

    public function offres(): HasMany
    {
        return $this->hasMany(OffreFournisseur::class);
    }

    public function offreSelectionnee(): BelongsTo
    {
        return $this->belongsTo(OffreFournisseur::class, 'offre_selectionnee_id');
    }

    public function getMeilleureOffreAttribute(): ?OffreFournisseur
    {
        return $this->offres()
            ->where('offre_technique', 'CONFORME')
            ->orderByDesc('score')
            ->first();
    }

    public function getNombreOffresAttribute(): int
    {
        return $this->offres()->count();
    }

    public function getMontantFormateAttribute(): string
    {
        return number_format($this->montant_estime ?? 0, 0, ',', ' ') . ' FCFA';
    }
}
