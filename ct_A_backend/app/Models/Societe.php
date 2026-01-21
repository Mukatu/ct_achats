<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Societe extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'societes';

    protected $fillable = [
        'code',
        'raison_sociale',
        'sigle',
        'forme_juridique',
        'niu',
        'rccm',
        'adresse_siege',
        'ville',
        'boite_postale',
        'pays',
        'telephone',
        'email',
        'site_web',
        'devise_defaut',
        'logo_url',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function exercices(): HasMany
    {
        return $this->hasMany(Exercice::class);
    }

    public function fournisseurs(): HasMany
    {
        return $this->hasMany(Fournisseur::class);
    }
}
