<?php

declare(strict_types=1);

namespace Duxbo\Seo\Redirects;

use Duxbo\Seo\Contracts\RedirectMatcher;
use Duxbo\Seo\Enums\RedirectMatchType;
use Duxbo\Seo\Exceptions\UnsafeRedirect;

/**
 * Runs {@see RedirectGuard}'s checks against a {@see Redirect} model instance
 * about to be saved, regardless of which code path is doing the saving.
 *
 * {@see RedirectRepository} already runs these checks before every write it
 * makes, so most of the time this is a second, redundant pass on data it has
 * already approved — cheap, and the price of the guarantee actually holding
 * for a row nobody went through the repository for: a seeder, a factory, an
 * artisan tinker session, or a future AI-driven integration that reaches for
 * `Redirect::create()` directly instead of the repository it doesn't know
 * exists.
 */
final class RedirectSaveGuard
{
    public function __construct(
        private readonly RedirectGuard $guard,
        private readonly RedirectMatcher $matcher,
    ) {
    }

    /**
     * @throws UnsafeRedirect
     */
    public function assertSafe(Redirect $redirect): void
    {
        // A column this guard cares about can still be unset on a row built
        // outside RedirectRepository (a bare `new Redirect(['target' => ...])`
        // before every required column is filled in) — fall back to values
        // that make every check here a safe no-op rather than a TypeError,
        // and let the database's own NOT NULL constraints reject the row as
        // they always would.
        $this->guard->assertPatternIsSafe(
            $redirect->source_type ?? RedirectMatchType::Exact,
            $redirect->source_path ?? '',
        );
        $this->guard->assertTargetIsSafe($redirect->target);

        $status = $redirect->status_code;

        if ($status === null || ! $status->redirects() || $redirect->is_active !== true) {
            return;
        }

        $this->guard->assertNoLoop(
            $redirect->source_path ?? '',
            $redirect->target,
            fn (string $path): ?string => $this->matcher->match($path)?->target,
        );
    }
}
