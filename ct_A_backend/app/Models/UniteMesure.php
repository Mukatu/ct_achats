<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniteMesure extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'unites_mesure';

    protected $fillable = [
        'code',
        'libelle',
        'symbole',
        'type',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];
}
