<?php

declare(strict_types=1);

namespace Duxbo\Seo\Redirects;

use Duxbo\Seo\Contracts\RedirectMatcher;
use Duxbo\Seo\Enums\RedirectMatchType;
use Duxbo\Seo\Enums\RedirectType;

/**
 * The only supported way to create a redirect.
 *
 * Every safety check runs here, before the row exists. Validating on read
 * instead would mean a dangerous rule sits in the table until someone trips
 * over it.
 */
final class RedirectRepository
{
    public function __construct(
        private readonly RedirectGuard $guard,
        private readonly RedirectMatcher $matcher,
    ) {
    }

    public function create(
        string $source,
        ?string $target,
        RedirectType $status = RedirectType::MovedPermanently,
        RedirectMatchType $type = RedirectMatchType::Exact,
        ?string $locale = null,
        ?string $notes = null,
    ): Redirect {
        $source = $type === RedirectMatchType::Regex ? $source : $this->guard->normalise($source);

        $this->guard->assertPatternIsSafe($type, $source);
        $this->guard->assertTargetIsSafe($target);

        if ($status->redirects()) {
            $this->guard->assertNoLoop($source, $target, fn (string $path): ?string => $this->targetFor($path));
        }

        $redirect = Redirect::query()->updateOrCreate(
            [
                'source_hash' => md5($source),
                'locale' => $locale,
            ],
            [
                'source_path' => $source,
                'source_type' => $type,
                'target' => $status->redirects() ? $target : null,
                'status_code' => $status,
                'is_active' => true,
                'notes' => $notes,
            ],
        );

        $this->matcher->flush();

        return $redirect;
    }

    public function delete(string $source, ?string $locale = null): void
    {
        Redirect::query()
            ->where('source_hash', md5($this->guard->normalise($source)))
            ->where('locale', $locale)
            ->delete();

        $this->matcher->flush();
    }

    public function disable(string $source, ?string $locale = null): void
    {
        Redirect::query()
            ->where('source_hash', md5($this->guard->normalise($source)))
            ->where('locale', $locale)
            ->update(['is_active' => false]);

        $this->matcher->flush();
    }

    private function targetFor(string $path): ?string
    {
        return $this->matcher->match($path)?->target;
    }
}
