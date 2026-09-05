<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;

/**
 * Looks up and runs the validator registered for one dynamic setting key.
 *
 * A key with no entry in `seo.settings.validators` is not rejected outright —
 * that would make every third-party key an application allowlists on its own
 * (a project can add to `seo.settings.keys` too) impossible to write without
 * also shipping a validator class for it, which is a call this package
 * cannot make on someone else's behalf. What guards against silently
 * forgetting one for a key *this package* ships is a structural test
 * asserting every entry in `seo.settings.keys` has a matching validator.
 */
final class SettingValidatorRegistry
{
    public function __construct(
        private readonly Config $config,
        private readonly Application $app,
    ) {
    }

    public function assertValid(string $key, mixed $value): void
    {
        $this->resolve($key)?->validate($key, $value);
    }

    private function resolve(string $key): ?SettingValueValidator
    {
        /** @var array<string, class-string<SettingValueValidator>> $map */
        $map = $this->config->get('seo.settings.validators', []);

        $class = $map[$key] ?? null;

        if ($class === null) {
            return null;
        }

        /** @var SettingValueValidator */
        return $this->app->make($class);
    }
}
