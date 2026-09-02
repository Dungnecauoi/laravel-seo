<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Middleware;

use Closure;
use Duxbo\Seo\Contracts\RedirectMatcher;
use Duxbo\Seo\NotFound\NotFoundLogger;
use Duxbo\Seo\Redirects\Redirect;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies redirects and records 404s, after the router has had its turn.
 *
 * Checking only once the response is already a 404 is the cheap arrangement:
 * redirect rules are few, almost every request matches a real route, and this
 * way the common path costs nothing at all. Set seo.redirects.eager to check
 * before routing instead, which suits a large imported rule set.
 */
final class HandleNotFound
{
    public function __construct(
        private readonly RedirectMatcher $matcher,
        private readonly NotFoundLogger $logger,
        private readonly Config $config,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->eager()) {
            $redirect = $this->redirectFor($request);

            if ($redirect !== null) {
                return $redirect;
            }
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        if (! $this->eager()) {
            $redirect = $this->redirectFor($request);

            if ($redirect !== null) {
                return $redirect;
            }
        }

        $this->logger->log($request);

        return $response;
    }

    private function redirectFor(Request $request): ?Response
    {
        if ($this->config->get('seo.redirects.enabled', true) !== true) {
            return null;
        }

        $match = $this->matcher->match($request->getPathInfo(), app()->getLocale());

        if ($match === null) {
            return null;
        }

        $this->recordHit($match->ruleId);

        if (! $match->redirects()) {
            // 410 and 451 end the request rather than sending it anywhere.
            return new Response('', $match->status->value);
        }

        $target = (string) $match->target;

        if ($this->config->get('seo.redirects.keep_query', true) === true) {
            $target = $this->withQuery($target, $request->getQueryString());
        }

        return new RedirectResponse($target, $match->status->value);
    }

    private function withQuery(string $target, ?string $query): string
    {
        if ($query === null || $query === '' || str_contains($target, '?')) {
            return $target;
        }

        return $target.'?'.$query;
    }

    private function recordHit(int|string $id): void
    {
        // Counting must not slow the response down or fail the redirect, and a
        // deadlock on a hot rule is not worth a lost visitor.
        try {
            DB::table((new Redirect())->getTable())
                ->where('id', $id)
                ->update([
                    'hits' => DB::raw('hits + 1'),
                    'last_hit_at' => Carbon::now(),
                ]);
        } catch (\Throwable) {
            // Deliberately swallowed.
        }
    }

    private function eager(): bool
    {
        return $this->config->get('seo.redirects.eager', false) === true;
    }
}
