<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'phone',
        'password',
        'avatar_url',
        'is_active',
        'permissions',
        'last_login_at',
        'last_logout_at',
        'last_login_ip',
        'last_user_agent',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_logout_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    public function hasRole(string|array $roles): bool
    {
        $name = $this->role?->name;

        if ($name === null) {
            return false;
        }

        $roles = (array) $roles;

        return in_array($name, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Rôles qu’un acteur peut assigner à la création / modification d’un compte.
     * Super : tous. RH : opérationnels seulement (pas Super, pas autre RH).
     *
     * @return list<string>
     */
    public function assignableRoleNames(): array
    {
        if ($this->isSuperAdmin()) {
            return Role::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->pluck('name')
                ->all();
        }

        // Admin / RH système + rôles personnalisés type RH (grille de permissions)
        if ($this->usesGranularPermissions()) {
            return ['sous_admin', 'conseiller', 'agent'];
        }

        return [];
    }

    public function canAssignRoleName(?string $roleName): bool
    {
        if ($roleName === null || $roleName === '') {
            return false;
        }

        return in_array($roleName, $this->assignableRoleNames(), true);
    }

    /**
     * Super : tout. RH (système ou perso) : uniquement agent / conseiller / sous-admin.
     */
    public function canAdministerAccount(User $target): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->usesGranularPermissions()) {
            return false;
        }

        return $this->canAssignRoleName($target->role?->name);
    }

    /**
     * Droits effectifs du compte RH / rôle personnalisé web
     * (null / vide → défauts complets pour rétrocompat).
     *
     * @return list<string>
     */
    public function effectivePermissions(): array
    {
        if (! $this->usesGranularPermissions()) {
            return [];
        }

        $stored = $this->permissions;
        if (! is_array($stored) || $stored === []) {
            return \App\Support\StaffPermissions::defaults();
        }

        return \App\Support\StaffPermissions::sanitize($stored);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->effectivePermissions(), true);
    }

    /**
     * Compte avec grille de permissions (Admin/RH ou rôle personnalisé).
     */
    public function usesGranularPermissions(): bool
    {
        return $this->hasRole(['admin', 'rh']) || $this->isCustomRole();
    }

    /**
     * Rôle créé via l’UI Super (hors seed système).
     */
    public function isCustomRole(): bool
    {
        $name = $this->role?->name;

        return $name !== null && ! in_array($name, Role::SYSTEM_NAMES, true);
    }

    /**
     * Super : toujours. RH / rôle perso : selon cases. Autres : false.
     */
    public function staffCan(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->usesGranularPermissions()) {
            return $this->hasPermission($permission);
        }

        return false;
    }

    public function isAdminStaff(): bool
    {
        // Accès supervision web : tous sauf agent / conseiller (mobile only).
        // Inclut les rôles personnalisés créés par le Super.
        if ($this->role === null) {
            return false;
        }

        return ! $this->isFieldUser();
    }

    /** Utilisateur terrain (mobile) : agent ou conseiller — données perso uniquement. */
    public function isFieldUser(): bool
    {
        return $this->hasRole(['agent', 'conseiller']);
    }

    /** Peut scanner / synchroniser des pointages (agent/conseiller ou staff avec fiche liée). */
    public function canSelfPointage(): bool
    {
        return $this->agent !== null && ($this->isFieldUser() || $this->isAdminStaff());
    }

    /**
     * Sur l’app mobile, restreindre aux données de sa propre fiche agent
     * (évite qu’un admin voie tous les pointages via le token mobile).
     */
    public function shouldScopeToOwnAgent(?string $tokenName = null): bool
    {
        if (! $this->agent) {
            return false;
        }
        if ($this->isFieldUser()) {
            return true;
        }
        $name = strtolower((string) $tokenName);

        return str_contains($name, 'pointage_mobile') || str_contains($name, 'mobile');
    }

    /** Fenêtre d’activité (minutes) pour considérer une session « en ligne ». */
    public static function onlineThresholdMinutes(): int
    {
        return 5;
    }

    public static function onlineThreshold(): \Carbon\Carbon
    {
        return now()->subMinutes(static::onlineThresholdMinutes());
    }

    /**
     * Scope / contrainte SQL : tokens encore considérés actifs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Laravel\Sanctum\PersonalAccessToken>|\Illuminate\Database\Eloquent\Relations\MorphMany  $query
     */
    public static function constrainActiveTokens($query): void
    {
        $threshold = static::onlineThreshold();

        $query->where(function ($q) use ($threshold) {
            $q->where('last_used_at', '>=', $threshold)
                ->orWhere(function ($q2) use ($threshold) {
                    $q2->whereNull('last_used_at')
                        ->where('created_at', '>=', $threshold);
                });
        });
    }

    public function activeSessionsCount(): int
    {
        if (isset($this->active_sessions_count)) {
            return (int) $this->active_sessions_count;
        }

        $query = $this->tokens();
        static::constrainActiveTokens($query);

        return (int) $query->count();
    }

    public function isOnline(): bool
    {
        return $this->activeSessionsCount() > 0;
    }

    public function lastSeenAt(): ?\Carbon\Carbon
    {
        if (! empty($this->tokens_max_last_used_at)) {
            return \Carbon\Carbon::parse($this->tokens_max_last_used_at);
        }

        $lastUsed = $this->tokens()->max('last_used_at');
        if ($lastUsed) {
            return \Carbon\Carbon::parse($lastUsed);
        }

        $lastCreated = $this->tokens()->max('created_at');

        return $lastCreated ? \Carbon\Carbon::parse($lastCreated) : $this->last_login_at;
    }
}
