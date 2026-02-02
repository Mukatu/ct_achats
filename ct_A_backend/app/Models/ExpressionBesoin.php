<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\StatutEB;

class ExpressionBesoin extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'expressions_besoin';

    protected $fillable = [
        'societe_id',
        'numero',
        'reference_origine',
        'date_expression',
        'zone_id',
        'direction_id',
        'service_id',
        'demandeur_id',
        'demandeur_nom',
        'objet',
        'description_detaillee',
        'quantite_souhaitee',
        'date_besoin',
        'estimation',
        'acheteur_id',
        'fournisseur_suggere_id',
        'statut',
        'date_validation',
        'motif_rejet',
        'commentaire',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_expression' => 'date',
        'date_besoin' => 'date',
        'estimation' => 'decimal:0',
        'date_validation' => 'datetime',
        'statut' => StatutEB::class,
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

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    public function acheteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acheteur_id');
    }

    public function fournisseurSuggere(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_suggere_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function demandesAchat(): HasMany
    {
        return $this->hasMany(DemandeAchat::class);
    }

    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommande::class);
    }

    // Formatage du montant en FCFA
    public function getEstimationFormatteeAttribute(): string
    {
        return number_format($this->estimation ?? 0, 0, ',', ' ') . ' FCFA';
    }

    // Libellé du statut
    public function getStatutLibelleAttribute(): string
    {
        return $this->statut?->label() ?? '';
    }
}
