<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'services';

    protected $fillable = [
        'direction_id',
        'code',
        'libelle',
        'description',
        'responsable_id',
        'ordre_affichage',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'ordre_affichage' => 'integer',
    ];

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
