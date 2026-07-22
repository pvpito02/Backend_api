<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\DemandeStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DemandeService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function recordHistory(
        AbsenceRequest $demande,
        ?string $from,
        string $to,
        ?User $actor = null,
        ?string $detail = null,
    ): DemandeStatusHistory {
        return DemandeStatusHistory::query()->create([
            'absence_request_id' => $demande->id,
            'from_statut' => $from,
            'to_statut' => $to,
            'changed_by' => $actor?->id,
            'changed_by_label' => $actor?->name ?? 'Système',
            'detail' => $detail,
            'created_at' => now(),
        ]);
    }

    public function markAsEnCours(AbsenceRequest $demande, User $admin): AbsenceRequest
    {
        if ($demande->statut !== 'EN_ATTENTE') {
            return $demande;
        }

        return DB::transaction(function () use ($demande, $admin) {
            $from = $demande->statut;
            $demande->forceFill([
                'statut' => 'EN_COURS',
                'lue_par_admin_at' => now(),
                'lue_par_admin_id' => $admin->id,
            ])->save();

            $this->recordHistory($demande, $from, 'EN_COURS', $admin, 'Ouverture / lecture par admin');

            return $demande->fresh();
        });
    }

    public function decide(AbsenceRequest $demande, User $admin, string $decision, ?string $motifRejet = null, ?string $commentaire = null): AbsenceRequest
    {
        return DB::transaction(function () use ($demande, $admin, $decision, $motifRejet, $commentaire) {
            $from = $demande->statut;
            $to = $decision === 'APPROUVEE' ? 'APPROUVEE' : 'REJETEE';

            $demande->forceFill([
                'statut' => $to,
                'approuve_par' => $admin->id,
                'date_approbation' => now(),
                'motif_rejet' => $to === 'REJETEE' ? $motifRejet : null,
                'commentaire' => $commentaire,
                'lue_par_admin_at' => $demande->lue_par_admin_at ?? now(),
                'lue_par_admin_id' => $demande->lue_par_admin_id ?? $admin->id,
            ])->save();

            $this->recordHistory(
                $demande,
                $from,
                $to,
                $admin,
                $to === 'REJETEE' ? ($motifRejet ?? 'Rejetée') : 'Approuvée',
            );

            $agentUser = $demande->agent?->user;
            if ($agentUser) {
                $label = $demande->type_demande;
                $this->notifications->notifyUser(
                    $agentUser,
                    $to === 'APPROUVEE' ? "Demande {$label} approuvée" : "Demande {$label} rejetée",
                    $to === 'APPROUVEE'
                        ? "Votre demande {$label} a été approuvée."
                        : "Votre demande {$label} a été rejetée".($motifRejet ? " : {$motifRejet}" : '.'),
                    $to === 'APPROUVEE' ? 'approbation' : 'refus',
                    strtolower($label),
                    'AbsenceRequest',
                    $demande->id,
                    playSound: true,
                );
            }

            return $demande->fresh(['agent.user', 'agent.departement', 'history', 'approbateur']);
        });
    }

    public function cancel(AbsenceRequest $demande, User $actor): AbsenceRequest
    {
        return DB::transaction(function () use ($demande, $actor) {
            $from = $demande->statut;
            $demande->update(['statut' => 'ANNULEE']);
            $this->recordHistory($demande, $from, 'ANNULEE', $actor, 'Annulation');

            return $demande->fresh();
        });
    }
}
