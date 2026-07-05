<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records mutating web requests (POST/PUT/PATCH/DELETE) to the audit log.
 * Read requests are skipped to keep the log lean.
 */
class LogActivity
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->user() || $request->isMethod('GET')) {
            return;
        }

        // Skip noisy auth + asset routes.
        if ($request->routeIs('logout', 'login')) {
            return;
        }

        $route = optional($request->route())->getName() ?? $request->path();

        $this->logger->log(
            action: 'request.'.strtolower($request->method()),
            description: $route,
            properties: ['url' => $request->fullUrl()],
        );
    }
}
