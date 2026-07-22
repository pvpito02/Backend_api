<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbsenceRequest;
use App\Models\Agent;
use App\Models\Pointage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function pointages(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        $query = Pointage::query()->with(['agent.departement', 'site'])->latest('date_pointage')->latest('heure_pointage');

        if ($request->filled('from')) {
            $query->whereDate('date_pointage', '>=', $request->string('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date_pointage', '<=', $request->string('to'));
        }
        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }

        return $this->csvDownload('pointages.csv', [
            'Date', 'Heure', 'Matricule', 'Agent', 'Service', 'Type', 'Statut', 'Retard (min)', 'Site', 'Source',
        ], function () use ($query) {
            foreach ($query->cursor() as $p) {
                yield [
                    optional($p->date_pointage)->format('Y-m-d'),
                    $p->heure_pointage,
                    $p->agent?->matricule,
                    $p->agent?->nom_complet,
                    $p->agent?->departement?->nom,
                    $p->type,
                    $p->statut,
                    $p->late_minutes ?? 0,
                    $p->site?->name,
                    $p->source,
                ];
            }
        });
    }

    public function retards(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        $query = Pointage::query()
            ->with(['agent.departement'])
            ->where('type', 'ENTREE')
            ->where(function ($q) {
                $q->where('statut', 'RETARD')->orWhere('late_minutes', '>', 0);
            })
            ->latest('date_pointage')
            ->latest('heure_pointage');

        if ($request->filled('from')) {
            $query->whereDate('date_pointage', '>=', $request->string('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date_pointage', '<=', $request->string('to'));
        }

        return $this->csvDownload('retards.csv', [
            'Date', 'Agent', 'Matricule', 'Heure', 'Statut', 'Retard (min)', 'Service',
        ], function () use ($query) {
            foreach ($query->cursor() as $p) {
                yield [
                    optional($p->date_pointage)->format('Y-m-d'),
                    $p->agent?->nom_complet,
                    $p->agent?->matricule,
                    $p->heure_pointage,
                    $p->statut,
                    $p->late_minutes ?? 0,
                    $p->agent?->departement?->nom,
                ];
            }
        });
    }

    public function agents(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        $query = Agent::query()->with('departement')->orderBy('nom');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return $this->csvDownload('agents.csv', [
            'Matricule', 'Prénom', 'Nom', 'Poste', 'Service', 'Email', 'Téléphone', 'Statut', 'Actif',
        ], function () use ($query) {
            foreach ($query->cursor() as $a) {
                yield [
                    $a->matricule,
                    $a->prenom,
                    $a->nom,
                    $a->poste,
                    $a->departement?->nom,
                    $a->email,
                    $a->telephone,
                    $a->statut,
                    $a->is_active ? '1' : '0',
                ];
            }
        });
    }

    public function demandes(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        $query = AbsenceRequest::query()->with('agent')->latest('id');

        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->string('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->string('to'));
        }

        return $this->csvDownload('demandes.csv', [
            'ID', 'Matricule', 'Agent', 'Type', 'Début', 'Fin', 'Statut', 'Motif', 'Créée le',
        ], function () use ($query) {
            foreach ($query->cursor() as $d) {
                yield [
                    $d->id,
                    $d->agent?->matricule,
                    $d->agent?->nom_complet,
                    $d->type_demande,
                    optional($d->date_debut)->format('Y-m-d'),
                    optional($d->date_fin)->format('Y-m-d'),
                    $d->statut,
                    $d->motif,
                    optional($d->created_at)->format('Y-m-d H:i'),
                ];
            }
        });
    }

    /**
     * @param  callable(): \Generator<int, array<int, mixed>>  $rows
     */
    private function csvDownload(string $filename, array $headers, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, $headers, ';');
            foreach ($rows() as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
