<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\StatutContrat;
use App\Enums\PeriodiciteContrat;
use Carbon\Carbon;

class Contrat extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'contrats';

    protected $fillable = [
        'societe_id',
        'numero',
        'reference_externe',
        'type_contrat_id',
        'fournisseur_id',
        'zone_id',
        'direction_id',
        'service_id',
        'objet',
        'description',
        'date_signature',
        'date_debut',
        'date_fin',
        'reconduction_tacite',
        'preavis_jours',
        'periodicite',
        'montant_periodique',
        'montant_annuel',
        'taux_tva',
        'tva_incluse',
        'conditions_paiement',
        'jour_facturation',
        'responsable_id',
        'contact_fournisseur',
        'statut',
        'commentaire',
        'fichier_contrat',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_signature' => 'date',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'reconduction_tacite' => 'boolean',
        'preavis_jours' => 'integer',
        'montant_periodique' => 'decimal:0',
        'montant_annuel' => 'decimal:0',
        'taux_tva' => 'decimal:2',
        'tva_incluse' => 'boolean',
        'jour_facturation' => 'integer',
        'periodicite' => PeriodiciteContrat::class,
        'statut' => StatutContrat::class,
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
            $model->calculerMontantAnnuel();
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
            $model->calculerMontantAnnuel();
        });
    }

    public function calculerMontantAnnuel(): void
    {
        if ($this->periodicite && $this->montant_periodique) {
            $this->montant_annuel = $this->montant_periodique * $this->periodicite->nombreEcheancesParAn();
        }
    }

    // Relations
    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function typeContrat(): BelongsTo
    {
        return $this->belongsTo(TypeContrat::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
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

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function echeances(): HasMany
    {
        return $this->hasMany(EcheanceContrat::class)->orderBy('date_echeance');
    }

    // Scopes
    public function scopeActif($query)
    {
        return $query->where('statut', StatutContrat::ACTIF);
    }

    public function scopeARenouveler($query, $jours = 90)
    {
        return $query->where('statut', StatutContrat::ACTIF)
            ->whereNotNull('date_fin')
            ->where('date_fin', '<=', now()->addDays($jours));
    }

    public function scopeEnCours($query)
    {
        return $query->whereIn('statut', [StatutContrat::ACTIF, StatutContrat::SUSPENDU]);
    }

    // Accesseurs
    public function getMontantPeriodiqueTtcAttribute(): float
    {
        if ($this->tva_incluse) {
            return $this->montant_periodique;
        }
        return $this->montant_periodique * (1 + $this->taux_tva / 100);
    }

    public function getMontantAnnuelFormateAttribute(): string
    {
        return number_format($this->montant_annuel ?? 0, 0, ',', ' ') . ' FCFA';
    }

    public function getMontantPeriodiqueFormateAttribute(): string
    {
        return number_format($this->montant_periodique ?? 0, 0, ',', ' ') . ' FCFA';
    }

    public function getJoursRestantsAttribute(): ?int
    {
        if (!$this->date_fin) {
            return null;
        }
        return now()->diffInDays($this->date_fin, false);
    }

    public function isExpireSoon($jours = 90): bool
    {
        return $this->date_fin && $this->jours_restants !== null && $this->jours_restants <= $jours;
    }

    public function isDureeIndeterminee(): bool
    {
        return $this->date_fin === null;
    }

    public function genererEcheances(?Carbon $dateDebut = null, ?Carbon $dateFin = null): int
    {
        $debut = $dateDebut ?? $this->date_debut;
        $fin = $dateFin ?? $this->date_fin ?? $debut->copy()->addYear();

        $count = 0;
        $current = $debut->copy();
        $nombreMois = $this->periodicite->nombreMois();

        if ($nombreMois === 0) {
            return 0;
        }

        while ($current->lte($fin)) {
            $periode = $this->formatPeriode($current);

            $exists = $this->echeances()->where('periode', $periode)->exists();

            if (!$exists) {
                $this->echeances()->create([
                    'numero' => $this->numero . '/' . str_pad($count + 1, 2, '0', STR_PAD_LEFT),
                    'periode' => $periode,
                    'date_echeance' => $current->copy()->day($this->jour_facturation ?? 1),
                    'montant_prevu' => $this->montant_periodique,
                    'statut' => 'A_VENIR',
                    'created_by' => auth()->id(),
                ]);
                $count++;
            }

            $current->addMonths($nombreMois);
        }

        return $count;
    }

    protected function formatPeriode(Carbon $date): string
    {
        return match($this->periodicite) {
            PeriodiciteContrat::MENSUEL => $date->format('Y-m'),
            PeriodiciteContrat::TRIMESTRIEL => $date->format('Y') . '-Q' . ceil($date->month / 3),
            PeriodiciteContrat::SEMESTRIEL => $date->format('Y') . '-S' . ($date->month <= 6 ? 1 : 2),
            PeriodiciteContrat::ANNUEL => $date->format('Y'),
            default => $date->format('Y-m'),
        };
    }
}
