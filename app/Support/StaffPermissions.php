<?php

namespace App\Support;

/**
 * Catalogue des droits opérationnels assignables à un compte RH (admin).
 * Le Super Admin coche / décoche à la création ou modification du compte.
 */
final class StaffPermissions
{
    /** @var array<string, string> */
    public const CATALOG = [
        'agents.manage' => 'Agents et dossiers',
        'departements.manage' => 'Départements',
        'demandes.decide' => 'Demandes (approuver / rejeter)',
        'overtime.decide' => 'Heures supplémentaires',
        'pointages.manage' => 'Pointages et anomalies',
        'qr.manage' => 'QR Codes',
        'planning.manage' => 'Planning et horaires',
        'calendrier.manage' => 'Calendrier officiel',
        'missions.manage' => 'Missions',
        'sanctions.manage' => 'Sanctions',
        'retraites.manage' => 'Retraites',
        'rapports.export' => 'Rapports, stats et exports',
        'parametres.manage' => 'Paramètres applicatifs',
        'sites.manage' => 'Sites / bornes',
        'annonces.manage' => 'Annonces',
        'utilisateurs.manage' => 'Comptes (agent, conseiller, sous-admin)',
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    /**
     * @return list<string>
     */
    public static function defaults(): array
    {
        return self::keys();
    }

    /**
     * @param  mixed  $input
     * @return list<string>
     */
    public static function sanitize(mixed $input): array
    {
        if (! is_array($input)) {
            return self::defaults();
        }

        $allowed = self::keys();
        $out = [];
        foreach ($input as $key) {
            if (is_string($key) && in_array($key, $allowed, true)) {
                $out[] = $key;
            }
        }

        return array_values(array_unique($out));
    }
}
