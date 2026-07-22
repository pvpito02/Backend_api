<?php

namespace Database\Seeders;

use App\Models\AbsenceRequest;
use App\Models\Agent;
use App\Models\User;
use App\Services\DemandeService;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;

class DemandeSeeder extends Seeder
{
    public function run(): void
    {
        $agent = Agent::query()->where('matricule', 'EMP001')->first();
        $admin = User::query()->where('email', 'admin@sandiara.sn')->first();

        if (! $agent || ! $admin) {
            return;
        }

        $demandeService = app(DemandeService::class);
        $notifService = app(NotificationService::class);

        $demande = AbsenceRequest::query()->updateOrCreate(
            [
                'agent_id' => $agent->id,
                'type_demande' => 'CONGE',
                'date_debut' => '2026-08-10',
                'date_fin' => '2026-08-15',
            ],
            [
                'motif' => 'Congé annuel — repos familial',
                'extra_json' => ['type_conge' => 'annuel'],
                'statut' => 'EN_ATTENTE',
            ]
        );

        if ($demande->history()->count() === 0) {
            $demandeService->recordHistory($demande, null, 'EN_ATTENTE', $agent->user, 'Seed démo');
            $notifService->notifyMany(
                $notifService->adminStaffUsers(),
                'Nouvelle demande CONGE',
                'Demande de congé seed (EMP001).',
                'confirmation',
                'conge',
                'AbsenceRequest',
                $demande->id,
                playSound: true,
            );
        }

        AbsenceRequest::query()->updateOrCreate(
            [
                'agent_id' => $agent->id,
                'type_demande' => 'PERMISSION',
                'date_debut' => now()->toDateString(),
                'date_fin' => now()->toDateString(),
                'heure_debut' => '10:00:00',
                'heure_fin' => '12:00:00',
            ],
            [
                'motif' => 'RDV médical',
                'extra_json' => ['motif_permission' => 'RDV médical'],
                'statut' => 'EN_ATTENTE',
            ]
        );
    }
}
