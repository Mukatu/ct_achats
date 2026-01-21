<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'zones';

    protected $fillable = [
        'societe_id',
        'code',
        'libelle',
        'ville',
        'adresse',
        'telephone',
        'responsable_id',
        'ordre_affichage',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'ordre_affichage' => 'integer',
    ];

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function directions(): HasMany
    {
        return $this->hasMany(Direction::class)->orderBy('ordre_affichage');
    }
}
