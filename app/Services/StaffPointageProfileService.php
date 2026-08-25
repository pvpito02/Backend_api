<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Crée / met à jour la fiche Agent + QR pour les comptes qui pointent hors rôle « agent »
 * (super_admin, admin, sous_admin, conseiller, …).
 */
class StaffPointageProfileService
{
    public const STAFF_ROLES = ['super_admin', 'admin', 'sous_admin', 'conseiller', 'rh', 'direction'];

    /** Préfixe matricule si aucun service n’est fourni. */
    private const ROLE_CODES = [
        'super_admin' => 'SAD',
        'admin' => 'ADM',
        'sous_admin' => 'SOU',
        'conseiller' => 'CON',
        'rh' => 'RH',
        'direction' => 'DIR',
    ];

    public function __construct(private readonly MatriculeGenerator $matricules) {}

    public static function isStaffRole(?string $roleName): bool
    {
        if ($roleName === null || $roleName === '') {
            return false;
        }

        // Terrain mobile only — pas de fiche staff auto
        if (in_array($roleName, ['agent'], true)) {
            return false;
        }

        // Système staff + conseiller + tout rôle personnalisé (accès web / pointage staff)
        return in_array($roleName, self::STAFF_ROLES, true)
            || ! in_array($roleName, Role::SYSTEM_NAMES, true);
    }

    /**
     * Assure un Agent lié + un QR actif pour un utilisateur staff.
     */
    public function ensureFor(User $user, array $options = []): Agent
    {
        $user->loadMissing('role', 'agent');
        $roleName = $user->role?->name;
        if (! self::isStaffRole($roleName)) {
            throw new \InvalidArgumentException('Profil pointage staff réservé aux rôles administratifs.');
        }

        $names = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];
        $prenom = (string) ($options['prenom'] ?? $names[0] ?? 'Staff');
        $nom = (string) ($options['nom'] ?? $names[1] ?? $user->name);
        $departementId = array_key_exists('departement_id', $options)
            ? $options['departement_id']
            : $user->agent?->departement_id;
        $poste = (string) ($options['poste']
            ?? $user->agent?->poste
            ?? ($user->role?->display_name ?: 'Staff'));

        $agent = $user->agent;
        if (! $agent) {
            $prefixOverride = $departementId
                ? null
                : (self::ROLE_CODES[$roleName] ?? 'STF');

            $matricule = $this->matricules->generate(
                $departementId ? (int) $departementId : null,
                null,
                $prefixOverride,
            );

            $agent = Agent::query()->create([
                'user_id' => $user->id,
                'matricule' => $matricule,
                'prenom' => $prenom ?: 'Staff',
                'nom' => $nom ?: $user->name,
                'poste' => $poste,
                'departement_id' => $departementId,
                'email' => $user->email,
                'telephone' => $user->phone,
                'photo_url' => $user->avatar_url,
                'statut' => 'Actif',
                'is_active' => (bool) $user->is_active,
                'date_entree' => now()->toDateString(),
            ]);
        } else {
            $agent->fill([
                'prenom' => $prenom ?: $agent->prenom,
                'nom' => $nom ?: $agent->nom,
                'poste' => $poste,
                'departement_id' => $departementId ?? $agent->departement_id,
                'email' => $user->email,
                'telephone' => $user->phone ?: $agent->telephone,
                'photo_url' => $user->avatar_url ?: $agent->photo_url,
                'statut' => $user->is_active ? 'Actif' : 'Inactif',
                'is_active' => (bool) $user->is_active,
            ])->save();
        }

        $this->ensureActiveQr($agent);

        return $agent->fresh(['departement', 'qrCodes']);
    }

    public function ensureActiveQr(Agent $agent): QrCode
    {
        $existing = QrCode::query()
            ->where('agent_id', $agent->id)
            ->where('statut', 'ACTIF')
            ->latest('id')
            ->first();

        if ($existing) {
            $existing->refreshExpiredStatus();
            $existing->refresh();
            if ($existing->statut === 'ACTIF') {
                return $existing;
            }
        }

        QrCode::query()
            ->where('agent_id', $agent->id)
            ->where('statut', 'ACTIF')
            ->update(['statut' => 'REVOQUE']);

        return QrCode::query()->create([
            'agent_id' => $agent->id,
            'code' => sprintf('SANDIARA:%s:%s', $agent->matricule, Str::upper(Str::random(8))),
            'issued_at' => now(),
            'expires_at' => null,
            'statut' => 'ACTIF',
        ]);
    }
}
