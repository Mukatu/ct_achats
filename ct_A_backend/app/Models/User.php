<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, SoftDeletes;

    protected $fillable = [
        'matricule',
        'nom',
        'prenom',
        'email',
        'password',
        'telephone',
        'service_id',
        'manager_id',
        'est_acheteur',
        'est_valideur',
        'seuil_validation',
        'actif',
        'date_entree',
        'date_sortie',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'est_acheteur' => 'boolean',
        'est_valideur' => 'boolean',
        'seuil_validation' => 'decimal:0',
        'actif' => 'boolean',
        'date_entree' => 'date',
        'date_sortie' => 'date',
        'derniere_connexion' => 'datetime',
    ];

    protected $appends = ['nom_complet'];

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordonnes(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->using(RoleUser::class)
            ->withPivot(['zone_id', 'direction_id', 'date_debut', 'date_fin', 'actif'])
            ->withTimestamps();
    }

    public function expressionsBesoins(): HasMany
    {
        return $this->hasMany(ExpressionBesoin::class, 'demandeur_id');
    }

    public function demandesAchat(): HasMany
    {
        return $this->hasMany(DemandeAchat::class, 'demandeur_id');
    }

    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommande::class, 'acheteur_id');
    }

    public function hasRole(string $roleCode): bool
    {
        return $this->roles()->where('code', $roleCode)->where('role_user.actif', true)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        foreach ($this->roles as $role) {
            $permissions = $role->permissions ?? [];
            if (in_array('all', $permissions) || in_array($permission, $permissions)) {
                return true;
            }
        }
        return false;
    }

    public function getDirection(): ?Direction
    {
        return $this->service?->direction;
    }

    public function getZone(): ?Zone
    {
        return $this->getDirection()?->zone;
    }
}
