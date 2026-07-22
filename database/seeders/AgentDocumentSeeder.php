<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentDocument;
use App\Models\User;
use Illuminate\Database\Seeder;

class AgentDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::query()->where('email', 'admin@sandiara.sn')->value('id');
        $agent = Agent::query()->where('matricule', 'EMP001')->first();

        if (! $agent) {
            return;
        }

        foreach (['PHOTO', 'CONTRAT', 'CNI'] as $type) {
            AgentDocument::query()->updateOrCreate(
                [
                    'agent_id' => $agent->id,
                    'type_document' => $type,
                ],
                [
                    'file_path' => null,
                    'is_present' => $type !== 'HISTORIQUE',
                    'uploaded_by' => $adminId,
                    'notes' => $type === 'PHOTO' ? 'Photo profil (référence externe)' : 'Présent (démo sans fichier)',
                ]
            );
        }

        AgentDocument::query()->updateOrCreate(
            [
                'agent_id' => $agent->id,
                'type_document' => 'HISTORIQUE',
            ],
            [
                'is_present' => false,
                'uploaded_by' => $adminId,
                'notes' => 'Manquant',
            ]
        );
    }
}
