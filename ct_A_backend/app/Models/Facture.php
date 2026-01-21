<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\StatutFacture;
use App\Enums\StatutPaiement;
use App\Enums\TypeFacture;

class Facture extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'factures';

    protected $fillable = [
        'societe_id',
        'numero_interne',
        'numero_fournisseur',
        'fournisseur_id',
        'bon_commande_id',
        'type_facture',
        'date_facture',
        'date_reception',
        'date_echeance',
        'montant_ht',
        'taux_tva',
        'montant_tva',
        'montant_ttc',
        'retenue_source',
        'net_a_payer',
        'devise',
        'statut',
        'statut_paiement',
        'date_validation',
        'valideur_id',
        'date_paiement',
        'reference_paiement',
        'montant_paye',
        'ecart_rapprochement',
        'motif_ecart',
        'fichier_facture_url',
        'commentaire',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_facture' => 'date',
        'date_reception' => 'date',
        'date_echeance' => 'date',
        'date_validation' => 'datetime',
        'date_paiement' => 'datetime',
        'montant_ht' => 'decimal:0',
        'taux_tva' => 'decimal:2',
        'montant_tva' => 'decimal:0',
        'montant_ttc' => 'decimal:0',
        'retenue_source' => 'decimal:0',
        'net_a_payer' => 'decimal:0',
        'montant_paye' => 'decimal:0',
        'ecart_rapprochement' => 'decimal:0',
        'type_facture' => TypeFacture::class,
        'statut' => StatutFacture::class,
        'statut_paiement' => StatutPaiement::class,
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

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class);
    }

    public function valideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valideur_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneFacture::class)->orderBy('numero_ligne');
    }

    // Calculer le solde restant
    public function getSoldeRestant(): float
    {
        return $this->net_a_payer - ($this->montant_paye ?? 0);
    }

    // Verifier si la facture est en retard
    public function isEnRetard(): bool
    {
        if ($this->statut_paiement === StatutPaiement::PAYEE) {
            return false;
        }
        return $this->date_echeance && $this->date_echeance->isPast();
    }

    // Calculer le nombre de jours de retard
    public function getJoursRetard(): int
    {
        if (!$this->isEnRetard()) {
            return 0;
        }
        return $this->date_echeance->diffInDays(now());
    }
}
