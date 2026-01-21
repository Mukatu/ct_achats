<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'roles';

    protected $fillable = [
        'code',
        'libelle',
        'description',
        'permissions',
        'est_systeme',
        'actif',
    ];

    protected $casts = [
        'permissions' => 'array',
        'est_systeme' => 'boolean',
        'actif' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot(['zone_id', 'direction_id', 'date_debut', 'date_fin', 'actif'])
            ->withTimestamps();
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        return in_array('all', $permissions) || in_array($permission, $permissions);
    }
}
