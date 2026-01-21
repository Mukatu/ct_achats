<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\StatutBR;
use App\Enums\TypeReception;

class Reception extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'receptions';

    protected $fillable = [
        'societe_id',
        'numero',
        'bon_commande_id',
        'date_reception',
        'receptionnaire_id',
        'lieu_reception',
        'type_reception',
        'numero_bl_fournisseur',
        'date_bl_fournisseur',
        'numero_tracking',
        'transporteur',
        'statut',
        'commentaire',
        'fichier_bl_url',
        'created_by',
    ];

    protected $casts = [
        'date_reception' => 'date',
        'date_bl_fournisseur' => 'date',
        'type_reception' => TypeReception::class,
        'statut' => StatutBR::class,
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
                if (!$model->receptionnaire_id) {
                    $model->receptionnaire_id = auth()->id();
                }
            }
        });
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class);
    }

    public function receptionnaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receptionnaire_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneReception::class)->orderBy('numero_ligne');
    }

    // Calculer les totaux
    public function getTotalQuantiteAttendue(): float
    {
        return $this->lignes->sum('quantite_attendue');
    }

    public function getTotalQuantiteRecue(): float
    {
        return $this->lignes->sum('quantite_recue');
    }

    public function getTotalQuantiteConforme(): float
    {
        return $this->lignes->sum('quantite_conforme');
    }

    public function getTauxConformite(): float
    {
        $total = $this->getTotalQuantiteRecue();
        if ($total == 0) return 0;
        return round(($this->getTotalQuantiteConforme() / $total) * 100, 2);
    }

    public function isComplete(): bool
    {
        return $this->getTotalQuantiteRecue() >= $this->getTotalQuantiteAttendue();
    }
}
