<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings\Validators;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Duxbo\Seo\Exceptions\InvalidSettingValue;

/**
 * An OAuth client secret or refresh token — Google issues these as opaque
 * strings with no fixed format this package can meaningfully check beyond
 * "actually a credential and not empty," which is what an empty-string
 * write (as opposed to `null`, which clears it) would otherwise silently
 * pass through as.
 */
final class SearchConsoleCredentialValidator implements SettingValueValidator
{
    private const MAX_LENGTH = 4096;

    public function validate(string $key, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || trim($value) === '') {
            throw InvalidSettingValue::make($key, 'must be a non-empty credential string, or null.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidSettingValue::make($key, sprintf('must be %d characters or fewer.', self::MAX_LENGTH));
        }
    }
}
