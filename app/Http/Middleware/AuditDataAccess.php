<?php

namespace App\Http\Middleware;

use App\Enums\AuditEvent;
use App\Services\AuditService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AuditDataAccess
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * @var list<string>
     */
    private array $exactRoutes = [
        'dashboard',
        'display',
        'environment.index',
        'live.index',
        'tracking.coverage',
        'hse.lsr.summary',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = Auth::user();

        if ($user === null) {
            return $response;
        }

        $role = $user->primaryRole();

        if ($role === null || ! $role->is_read_only) {
            return $response;
        }

        if ($response->getStatusCode() >= 400 || ! $this->isMeaningfulRead($request)) {
            return $response;
        }

        $this->audit->record(
            AuditEvent::DataAccess,
            description: 'Read-only user accessed data.',
            newValues: [
                'method' => $request->method(),
                'path' => $request->path(),
                'parameters' => $this->routeParameters($request),
                'query' => $request->except(['password', 'token']),
            ],
            user: $user,
            route: $request->route()?->getName() ?? $request->path(),
        );

        return $response;
    }

    private function isMeaningfulRead(Request $request): bool
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || $request->is('api/*')) {
            return false;
        }

        $name = $request->route()?->getName();
        if ($name === null || str_contains($name, '.api.')) {
            return false;
        }

        if (in_array($name, $this->exactRoutes, true)) {
            return true;
        }

        return str_ends_with($name, '.index')
            || str_ends_with($name, '.show')
            || str_ends_with($name, '.export')
            || str_ends_with($name, '.download');
    }

    /**
     * @return array<string, int|string|null>
     */
    private function routeParameters(Request $request): array
    {
        return collect($request->route()?->parameters() ?? [])
            ->map(static fn (mixed $value): int|string|null => $value instanceof Model
                ? $value->getKey()
                : (is_scalar($value) ? (string) $value : null))
            ->all();
    }
}
