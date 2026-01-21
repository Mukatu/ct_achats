<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NatureDepense extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'natures_depense';

    protected $fillable = [
        'societe_id',
        'code',
        'libelle',
        'description',
        'type',
        'compte_ohada',
        'parent_id',
        'niveau',
        'imputable',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'imputable' => 'boolean',
        'niveau' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(NatureDepense::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(NatureDepense::class, 'parent_id');
    }
}
