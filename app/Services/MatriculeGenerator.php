<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Departement;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Matricule auto : {SERVICE}-{ANNEE}{SUFFIXE}
 * Ex. FIN-2026K7M2P9
 */
class MatriculeGenerator
{
    /** Alphabet sans I/O/0/1 (lisibilité badge / QR). */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Codes courts stables par département. */
    private const SERVICE_CODES = [
        'TECH' => 'TEC',
        'ETAT_CIVIL' => 'ETC',
        'INFORMATIQUE' => 'INF',
        'COURRIER' => 'COU',
        'URBANISME' => 'URB',
        'ACCUEIL' => 'ACC',
        'ARCHIVES' => 'ARC',
        'FINANCES' => 'FIN',
        'SECRETARIAT' => 'SEC',
        'RH' => 'RH',
    ];

    public function generate(?int $departementId = null, Carbon|string|null $referenceDate = null): string
    {
        $year = Carbon::parse($referenceDate ?? now())->format('Y');
        $prefix = $this->serviceCode($departementId);

        for ($attempt = 0; $attempt < 40; $attempt++) {
            $matricule = sprintf('%s-%s%s', $prefix, $year, $this->randomSuffix(6));
            if (! Agent::query()->where('matricule', $matricule)->exists()) {
                return $matricule;
            }
        }

        throw new RuntimeException('Impossible de générer un matricule unique. Réessayez.');
    }

    public function serviceCode(?int $departementId): string
    {
        if (! $departementId) {
            return 'GEN';
        }

        $code = Departement::query()->whereKey($departementId)->value('code');
        if (! is_string($code) || $code === '') {
            return 'GEN';
        }

        $normalized = strtoupper(trim($code));
        if (isset(self::SERVICE_CODES[$normalized])) {
            return self::SERVICE_CODES[$normalized];
        }

        $compact = preg_replace('/[^A-Z0-9]/', '', $normalized) ?: 'GEN';

        return substr($compact, 0, 3);
    }

    private function randomSuffix(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
