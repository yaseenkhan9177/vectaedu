<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\School;
use App\Services\TenantService;
\Illuminate\Support\Facades\Log::info('tenant_db session: ' . session('tenant_db'));

class IdentifyTenant
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // First try session-based tenant (single domain setup)
        if (session('tenant_db')) {
            $this->tenantService->configureConnection(session('tenant_db'));
            return $next($request);
        }

        // Fallback: subdomain-based tenant
        $host = $request->getHost();
        $parts = explode('.', $host);

        // Only try subdomain if more than 2 parts
        if (count($parts) <= 2) {
            return $next($request);
        }

        $subdomain = $parts[0];

        $school = School::where('slug', $subdomain)
            ->where('status', 'active')
            ->first();

        if ($school) {
            $this->tenantService->configureConnection($school->database_name);
        }

        return $next($request);
    }
}