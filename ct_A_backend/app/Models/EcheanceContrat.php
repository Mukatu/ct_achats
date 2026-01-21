<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\StatutEcheance;

class EcheanceContrat extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'echeances_contrat';

    protected $fillable = [
        'contrat_id',
        'numero',
        'periode',
        'date_echeance',
        'date_facture',
        'numero_facture',
        'montant_prevu',
        'montant_facture',
        'montant_ht',
        'montant_tva',
        'montant_ttc',
        'statut',
        'date_paiement',
        'reference_paiement',
        'bon_commande_id',
        'commentaire',
        'created_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'date_echeance' => 'date',
        'date_facture' => 'date',
        'date_paiement' => 'date',
        'montant_prevu' => 'decimal:0',
        'montant_facture' => 'decimal:0',
        'montant_ht' => 'decimal:0',
        'montant_tva' => 'decimal:0',
        'montant_ttc' => 'decimal:0',
        'validated_at' => 'datetime',
        'statut' => StatutEcheance::class,
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });
    }

    // Relations
    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // Scopes
    public function scopeAVenir($query)
    {
        return $query->where('statut', StatutEcheance::A_VENIR);
    }

    public function scopeATraiter($query)
    {
        return $query->where('statut', StatutEcheance::A_TRAITER);
    }

    public function scopeEnRetard($query)
    {
        return $query->where('statut', StatutEcheance::A_TRAITER)
            ->where('date_echeance', '<', now());
    }

    public function scopeDuMois($query, $annee = null, $mois = null)
    {
        $annee = $annee ?? now()->year;
        $mois = $mois ?? now()->month;

        return $query->whereYear('date_echeance', $annee)
            ->whereMonth('date_echeance', $mois);
    }

    // Accesseurs
    public function getMontantPrevuFormateAttribute(): string
    {
        return number_format($this->montant_prevu ?? 0, 0, ',', ' ') . ' FCFA';
    }

    public function getMontantFactureFormateAttribute(): string
    {
        return number_format($this->montant_facture ?? 0, 0, ',', ' ') . ' FCFA';
    }

    public function getEcartMontantAttribute(): float
    {
        if (!$this->montant_facture) {
            return 0;
        }
        return $this->montant_facture - $this->montant_prevu;
    }

    public function isEnRetard(): bool
    {
        return $this->statut === StatutEcheance::A_TRAITER && $this->date_echeance->isPast();
    }

    public function getJoursRetardAttribute(): int
    {
        if (!$this->isEnRetard()) {
            return 0;
        }
        return $this->date_echeance->diffInDays(now());
    }

    // Actions
    public function marquerFacturee(
        string $numeroFacture,
        float $montantFacture,
        ?float $montantHt = null,
        ?float $montantTva = null,
        ?\DateTime $dateFacture = null
    ): void {
        $this->update([
            'statut' => StatutEcheance::EN_COURS,
            'numero_facture' => $numeroFacture,
            'montant_facture' => $montantFacture,
            'montant_ht' => $montantHt,
            'montant_tva' => $montantTva,
            'montant_ttc' => $montantFacture,
            'date_facture' => $dateFacture ?? now(),
        ]);
    }

    public function marquerPayee(string $referencePaiement, ?\DateTime $datePaiement = null): void
    {
        $this->update([
            'statut' => StatutEcheance::PAYE,
            'reference_paiement' => $referencePaiement,
            'date_paiement' => $datePaiement ?? now(),
            'validated_by' => auth()->id(),
            'validated_at' => now(),
        ]);
    }

    public function annuler(?string $commentaire = null): void
    {
        $this->update([
            'statut' => StatutEcheance::ANNULE,
            'commentaire' => $commentaire ?? $this->commentaire,
        ]);
    }
}
