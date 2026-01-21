<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\StatutFournisseur;
use App\Enums\TypeFournisseur;

class Fournisseur extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'fournisseurs';

    protected $fillable = [
        'societe_id',
        'code',
        'raison_sociale',
        'sigle',
        'forme_juridique',
        'niu',
        'rccm',
        'regime_fiscal',
        'adresse',
        'ville',
        'boite_postale',
        'pays',
        'telephone',
        'email',
        'site_web',
        'categorie_id',
        'type_fournisseur',
        'conditions_paiement',
        'devise_defaut',
        'taux_tva',
        'assujetti_tva',
        'statut',
        'note_evaluation',
        'commentaire_interne',
        'actif',
        'created_by',
    ];

    protected $casts = [
        'taux_tva' => 'decimal:2',
        'assujetti_tva' => 'boolean',
        'note_evaluation' => 'decimal:2',
        'actif' => 'boolean',
        'type_fournisseur' => TypeFournisseur::class,
        'statut' => StatutFournisseur::class,
    ];

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieFournisseur::class, 'categorie_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ContactFournisseur::class);
    }

    public function contactPrincipal(): BelongsTo
    {
        return $this->hasOne(ContactFournisseur::class)->where('est_principal', true);
    }

    public function ribs(): HasMany
    {
        return $this->hasMany(RibFournisseur::class);
    }

    public function ribPrincipal(): BelongsTo
    {
        return $this->hasOne(RibFournisseur::class)->where('est_principal', true);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentFournisseur::class);
    }

    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommande::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function isLocal(): bool
    {
        return $this->type_fournisseur === TypeFournisseur::LOCAL;
    }

    public function isActif(): bool
    {
        return $this->statut === StatutFournisseur::ACTIF && $this->actif;
    }

    // Vérifier si tous les documents obligatoires sont valides
    public function hasDocumentsValides(): bool
    {
        $documentsObligatoires = ['RCCM', 'NIU', 'PATENTE', 'CNSS', 'ATT_FISCALE', 'RIB'];
        
        foreach ($documentsObligatoires as $type) {
            $doc = $this->documents()->where('type_document', $type)->first();
            if (!$doc || $doc->statut !== 'VALIDE') {
                return false;
            }
        }
        
        return true;
    }
}
