<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings\Validators;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Duxbo\Seo\Exceptions\InvalidSettingValue;

/**
 * The IndexNow protocol itself accepts any string, but this package embeds
 * the value directly as a literal Laravel route URI segment
 * (`Route::get($key.'.txt', ...)`, see `routes/seo.php`). A `{`/`}` in the
 * value would register a *dynamic* route parameter instead of a fixed path —
 * `{evil}.txt` — matching any request path in that position and handing
 * back the real key from {@see \Duxbo\Seo\Http\Controllers\IndexNowKeyController},
 * which ignores the route parameter and always returns the configured key.
 * A `/` would split the value across route segments entirely. Restricting to
 * the charset every real key generator already produces (hex, base62, UUIDs)
 * closes that off without narrowing what a legitimate key looks like.
 */
final class IndexNowKeyValidator implements SettingValueValidator
{
    private const PATTERN = '/^[A-Za-z0-9_-]{1,128}$/';

    public function validate(string $key, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidSettingValue::make($key, 'must be 1-128 letters, digits, "_" or "-", or null.');
        }
    }
}
