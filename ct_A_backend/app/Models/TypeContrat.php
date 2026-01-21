<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeContrat extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'types_contrat';

    protected $fillable = [
        'code',
        'libelle',
        'description',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
