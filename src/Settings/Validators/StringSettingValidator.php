<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings\Validators;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Duxbo\Seo\Exceptions\InvalidSettingValue;

/**
 * Free-form text: a title, a description, a verification code. `null` clears
 * the field back to empty rather than to config/seo.php's own value — for
 * that, {@see \Duxbo\Seo\Settings\SettingsRepository::forget()} exists.
 *
 * The only real risk a generic text setting carries is size, not shape — an
 * over-long value has no legitimate use here and would otherwise sit
 * unbounded in `seo_settings` and get pushed into every request's live
 * config on every boot.
 */
final class StringSettingValidator implements SettingValueValidator
{
    private const MAX_LENGTH = 2000;

    public function validate(string $key, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value)) {
            throw InvalidSettingValue::make($key, 'must be a string or null.');
        }

        if (str_contains($value, "\0")) {
            throw InvalidSettingValue::make($key, 'must not contain a NUL byte.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidSettingValue::make($key, sprintf('must be %d characters or fewer.', self::MAX_LENGTH));
        }
    }
}
