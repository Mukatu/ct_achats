<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\StatutDA;
use App\Enums\TypeDemande;

class DemandeAchat extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'demandes_achat';

    protected $fillable = [
        'societe_id',
        'numero',
        'type_demande',
        'date_demande',
        'expression_besoin_id',
        'zone_id',
        'direction_id',
        'service_id',
        'demandeur_id',
        'objet',
        'description',
        'acheteur_id',
        'montant',
        'statut',
        'date_validation',
        'motif_rejet',
        'commentaire',
        'created_by',
        'updated_by',
        // Champs DAC - pièce justificative caisse
        'date_paiement_caisse',
        'reference_paiement',
        'montant_paye',
        'piece_justificative',
        'observations_cloture',
        'cloture_par',
        'date_cloture',
    ];

    protected $casts = [
        'date_demande' => 'date',
        'montant' => 'decimal:0',
        'date_validation' => 'datetime',
        'type_demande' => TypeDemande::class,
        'statut' => StatutDA::class,
        // Champs DAC
        'date_paiement_caisse' => 'date',
        'montant_paye' => 'decimal:2',
        'date_cloture' => 'datetime',
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

    public function expressionBesoin(): BelongsTo
    {
        return $this->belongsTo(ExpressionBesoin::class);
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

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneDemandeAchat::class)->orderBy('numero_ligne');
    }

    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommande::class);
    }

    public function getMontantTotalLignesAttribute(): float
    {
        return $this->lignes->sum(function ($ligne) {
            if ($ligne->offreSelectionnee) {
                return $ligne->offreSelectionnee->prix_unitaire * $ligne->quantite;
            }
            return $ligne->montant_estime ?? 0;
        });
    }

    public function getMontantFormateAttribute(): string
    {
        return number_format($this->montant ?? 0, 0, ',', ' ') . ' FCFA';
    }

    public function isDac(): bool
    {
        return $this->type_demande === TypeDemande::DAC;
    }

    /**
     * Relation vers l'utilisateur ayant clôturé la DAC
     */
    public function cloturePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cloture_par');
    }

    /**
     * Vérifie si la DAC est clôturée (payée avec justificatif)
     */
    public function isCloturee(): bool
    {
        return $this->isDac() && $this->date_cloture !== null;
    }

    /**
     * Vérifie si la DAC peut être clôturée
     */
    public function peutEtreCloturee(): bool
    {
        return $this->isDac()
            && $this->statut === StatutDA::TRAITE
            && !$this->isCloturee();
    }

    /**
     * Montant payé formaté
     */
    public function getMontantPayeFormateAttribute(): string
    {
        return number_format($this->montant_paye ?? 0, 0, ',', ' ') . ' FCFA';
    }
}
