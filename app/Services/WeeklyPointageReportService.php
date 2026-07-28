<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Pointage;
use App\Models\RemoteConfig;
use Carbon\Carbon;

/**
 * Rapport hebdomadaire de pointage (lundi → samedi), modèle mairie.
 */
class WeeklyPointageReportService
{
    /**
     * @return array<string, mixed>
     */
    public function generate(?string $date = null, ?string $signatoryName = null): array
    {
        $ref = $date
            ? Carbon::parse($date)->startOfDay()
            : Carbon::today();

        // Semaine civile lundi → samedi
        $monday = $ref->copy()->startOfWeek(Carbon::MONDAY);
        $saturday = $monday->copy()->addDays(5);

        $agents = Agent::query()
            ->where('is_active', true)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'matricule', 'prenom', 'nom']);

        $agentNames = $agents->mapWithKeys(
            fn (Agent $a) => [$a->id => trim("{$a->prenom} {$a->nom}")]
        );

        $days = [];
        for ($i = 0; $i < 6; $i++) {
            $day = $monday->copy()->addDays($i);
            $presentIds = Pointage::query()
                ->whereDate('date_pointage', $day)
                ->where('type', 'ENTREE')
                ->distinct()
                ->pluck('agent_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $presentIdsSet = $presentIds->flip();

            $presents = $agents
                ->filter(fn (Agent $a) => $presentIdsSet->has($a->id))
                ->values()
                ->map(fn (Agent $a) => [
                    'id' => $a->id,
                    'matricule' => $a->matricule,
                    'name' => $agentNames[$a->id],
                ]);

            $absents = $agents
                ->reject(fn (Agent $a) => $presentIdsSet->has($a->id))
                ->values()
                ->map(fn (Agent $a) => [
                    'id' => $a->id,
                    'matricule' => $a->matricule,
                    'name' => $agentNames[$a->id],
                ]);

            $days[] = [
                'date' => $day->toDateString(),
                'date_label' => $day->format('d-m-Y'),
                'weekday' => $this->weekdayFr($day),
                'weekday_short' => $this->weekdayFrShort($day),
                'day_number' => (int) $day->format('d'),
                'day_number_words' => $this->numberToFrenchWords((int) $day->format('d')),
                'month_name' => $this->monthFr($day),
                'year' => (int) $day->format('Y'),
                'is_future' => $day->isAfter(Carbon::today()),
                'presents' => $presents->all(),
                'absents' => $absents->all(),
                'presents_count' => $presents->count(),
                'absents_count' => $absents->count(),
                'absents_label' => $absents->isEmpty()
                    ? 'Néant'
                    : $absents->pluck('name')->implode(', '),
                'presents_label' => $presents->isEmpty()
                    ? 'Néant'
                    : $presents->pluck('name')->implode(', '),
            ];
        }

        $orgName = RemoteConfig::getValue('org_name', 'Mairie de Sandiara') ?: 'Mairie de Sandiara';
        $year = (int) $monday->format('Y');

        return [
            'title' => 'RAPPORT DU POINTAGE HEBDOMADAIRE',
            'org_name' => $orgName,
            'year' => $year,
            'year_words' => $this->yearToFrenchWords($year),
            'week_start' => $monday->toDateString(),
            'week_end' => $saturday->toDateString(),
            'week_start_label' => $monday->format('d-m-Y'),
            'week_end_label' => $saturday->format('d-m-Y'),
            'week_start_day' => (int) $monday->format('d'),
            'week_start_day_words' => $this->numberToFrenchWords((int) $monday->format('d')),
            'week_end_day' => (int) $saturday->format('d'),
            'week_end_day_words' => $this->numberToFrenchWords((int) $saturday->format('d')),
            'week_month_name' => $this->monthFr($monday),
            'week_range_words' => sprintf(
                'du %s (%s) au %s (%s) %s',
                $this->numberToFrenchWords((int) $monday->format('d')),
                $monday->format('d'),
                $this->numberToFrenchWords((int) $saturday->format('d')),
                $saturday->format('d'),
                $this->monthFr($monday)
            ),
            'agents_total' => $agents->count(),
            'signatory_name' => $signatoryName ?: '',
            'generated_at' => now()->toIso8601String(),
            'days' => $days,
        ];
    }

    private function weekdayFr(Carbon $day): string
    {
        return match ((int) $day->dayOfWeek) {
            Carbon::MONDAY => 'Lundi',
            Carbon::TUESDAY => 'Mardi',
            Carbon::WEDNESDAY => 'Mercredi',
            Carbon::THURSDAY => 'Jeudi',
            Carbon::FRIDAY => 'Vendredi',
            Carbon::SATURDAY => 'Samedi',
            default => 'Dimanche',
        };
    }

    private function weekdayFrShort(Carbon $day): string
    {
        return match ((int) $day->dayOfWeek) {
            Carbon::MONDAY => 'Lun',
            Carbon::TUESDAY => 'Mar',
            Carbon::WEDNESDAY => 'Mer',
            Carbon::THURSDAY => 'Jeu',
            Carbon::FRIDAY => 'Ven',
            Carbon::SATURDAY => 'Sam',
            default => 'Dim',
        };
    }

    private function monthFr(Carbon $day): string
    {
        return match ((int) $day->format('n')) {
            1 => 'janvier',
            2 => 'février',
            3 => 'mars',
            4 => 'avril',
            5 => 'mai',
            6 => 'juin',
            7 => 'juillet',
            8 => 'août',
            9 => 'septembre',
            10 => 'octobre',
            11 => 'novembre',
            12 => 'décembre',
            default => '',
        };
    }

    private function yearToFrenchWords(int $year): string
    {
        if ($year < 2000 || $year > 2099) {
            return (string) $year;
        }

        $rest = $year - 2000;
        if ($rest === 0) {
            return 'deux mille';
        }

        return 'deux mille '.$this->numberToFrenchWords($rest);
    }

    private function numberToFrenchWords(int $n): string
    {
        static $map = [
            0 => 'zéro', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre',
            5 => 'cinq', 6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf',
            10 => 'dix', 11 => 'onze', 12 => 'douze', 13 => 'treize', 14 => 'quatorze',
            15 => 'quinze', 16 => 'seize', 17 => 'dix-sept', 18 => 'dix-huit', 19 => 'dix-neuf',
            20 => 'vingt', 21 => 'vingt et un', 22 => 'vingt-deux', 23 => 'vingt-trois',
            24 => 'vingt-quatre', 25 => 'vingt-cinq', 26 => 'vingt-six', 27 => 'vingt-sept',
            28 => 'vingt-huit', 29 => 'vingt-neuf', 30 => 'trente', 31 => 'trente et un',
            32 => 'trente-deux', 40 => 'quarante', 50 => 'cinquante', 60 => 'soixante',
            70 => 'soixante-dix', 71 => 'soixante et onze', 72 => 'soixante-douze',
            80 => 'quatre-vingts', 90 => 'quatre-vingt-dix', 91 => 'quatre-vingt-onze',
            92 => 'quatre-vingt-douze', 99 => 'quatre-vingt-dix-neuf',
        ];

        if (isset($map[$n])) {
            return $map[$n];
        }

        if ($n < 40) {
            return 'trente-'.$map[$n - 30];
        }
        if ($n < 50) {
            return $n === 41 ? 'quarante et un' : 'quarante-'.$map[$n - 40];
        }
        if ($n < 60) {
            return $n === 51 ? 'cinquante et un' : 'cinquante-'.$map[$n - 50];
        }
        if ($n < 70) {
            return $n === 61 ? 'soixante et un' : 'soixante-'.$map[$n - 60];
        }
        if ($n < 80) {
            return 'soixante-'.$map[$n - 60];
        }
        if ($n < 90) {
            return 'quatre-vingt-'.$map[$n - 80];
        }
        if ($n < 100) {
            return 'quatre-vingt-'.$map[$n - 80];
        }

        return (string) $n;
    }
}
