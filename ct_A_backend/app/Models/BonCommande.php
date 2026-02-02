<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\StatutBC;
use App\Enums\TypeBC;

class BonCommande extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'bons_commande';

    protected $fillable = [
        'societe_id',
        'numero',
        'reference_origine',
        'type_bc',
        'date_bc',
        'demande_achat_id',
        'expression_besoin_id',
        'fournisseur_id',
        'contact_fournisseur_id',
        'zone_id',
        'direction_id',
        'demandeur_id',
        'acheteur_id',
        'objet',
        'nature_prestation',
        'montant_ht_xaf',
        'taux_tva',
        'montant_tva',
        'montant_ttc_xaf',
        'devise_etrangere',
        'montant_devise',
        'taux_change',
        'conditions_paiement',
        'conditions_livraison',
        'adresse_livraison',
        'personne_contact',
        'telephone_contact',
        'date_livraison_prevue',
        'numero_devis',
        'statut',
        'date_envoi_fournisseur',
        'date_accuse_reception',
        'fichier_bc_url',
        'commentaire',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_bc' => 'date',
        'montant_ht_xaf' => 'decimal:0',
        'taux_tva' => 'decimal:2',
        'montant_tva' => 'decimal:0',
        'montant_ttc_xaf' => 'decimal:0',
        'montant_devise' => 'decimal:2',
        'taux_change' => 'decimal:4',
        'date_livraison_prevue' => 'date',
        'date_envoi_fournisseur' => 'datetime',
        'date_accuse_reception' => 'date',
        'type_bc' => TypeBC::class,
        'statut' => StatutBC::class,
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
            // Calcul automatique des montants
            $model->calculerMontants();
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function calculerMontants(): void
    {
        $this->montant_tva = round($this->montant_ht_xaf * ($this->taux_tva / 100));
        $this->montant_ttc_xaf = $this->montant_ht_xaf + $this->montant_tva;
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function demandeAchat(): BelongsTo
    {
        return $this->belongsTo(DemandeAchat::class);
    }

    public function expressionBesoin(): BelongsTo
    {
        return $this->belongsTo(ExpressionBesoin::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function contactFournisseur(): BelongsTo
    {
        return $this->belongsTo(ContactFournisseur::class, 'contact_fournisseur_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
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
        return $this->hasMany(LigneBonCommande::class)->orderBy('numero_ligne');
    }

    public function receptions(): HasMany
    {
        return $this->hasMany(Reception::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    // Accesseurs formatés
    public function getMontantHtFormateAttribute(): string
    {
        return number_format($this->montant_ht_xaf ?? 0, 0, ',', ' ') . ' FCFA';
    }

    public function getMontantTtcFormateAttribute(): string
    {
        return number_format($this->montant_ttc_xaf ?? 0, 0, ',', ' ') . ' FCFA';
    }

    public function isInternational(): bool
    {
        return in_array($this->type_bc, [TypeBC::BCAI, TypeBC::BCI, TypeBC::IPO]);
    }

    public function isLocal(): bool
    {
        return in_array($this->type_bc, [TypeBC::BCAL, TypeBC::BCL]);
    }
}
