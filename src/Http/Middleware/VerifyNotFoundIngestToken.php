<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorizes the 404-ingest endpoint with a bare shared secret, not a
 * session — the caller is a separately-deployed front end's own backend,
 * with no cookie this application ever issued it. Compared with
 * `hash_equals()` rather than `===` for the same reason any secret
 * comparison against attacker-controlled input is: a naive `===` leaks how
 * many leading bytes matched through response timing.
 *
 * The route this guards is only ever registered when
 * `seo.not_found.ingest_token` is actually configured (see
 * `routes/seo.php`) — a project that never opts in exposes no route at all.
 */
final class VerifyNotFoundIngestToken
{
    private const HEADER = 'X-Seo-Ingest-Token';

    public function __construct(private readonly Config $config)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $configured = $this->config->get('seo.not_found.ingest_token');
        $supplied = (string) $request->header(self::HEADER, '');

        if (! is_string($configured) || $configured === '' || $supplied === '' || ! hash_equals($configured, $supplied)) {
            return new JsonResponse(['message' => 'Missing or invalid '.self::HEADER.' header.'], 401);
        }

        return $next($request);
    }
}
