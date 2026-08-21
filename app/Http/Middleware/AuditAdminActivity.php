<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Journalise les actions admin (créations, modifications, suppressions, exports…)
 * pour supervision Super Admin — sans données sensibles (mots de passe).
 */
class AuditAdminActivity
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->audit->logAdminRequest($request, $response);
        } catch (Throwable) {
            // Ne jamais faire échouer la requête métier à cause de l’audit
        }

        return $response;
    }
}
