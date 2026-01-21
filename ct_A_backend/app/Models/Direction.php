<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Direction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'directions';

    protected $fillable = [
        'zone_id',
        'code',
        'libelle',
        'libelle_court',
        'description',
        'responsable_id',
        'ordre_affichage',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'ordre_affichage' => 'integer',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('ordre_affichage');
    }

    public function expressionBesoins(): HasMany
    {
        return $this->hasMany(ExpressionBesoin::class);
    }
}
