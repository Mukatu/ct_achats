<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterePonderation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'criteres_ponderation';

    protected $fillable = [
        'societe_id',
        'code',
        'libelle',
        'description',
        'poids',
        'actif',
        'ordre',
    ];

    protected $casts = [
        'poids' => 'integer',
        'actif' => 'boolean',
        'ordre' => 'integer',
    ];

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }
}
