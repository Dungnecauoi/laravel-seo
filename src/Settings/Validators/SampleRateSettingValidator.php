<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings\Validators;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Duxbo\Seo\Exceptions\InvalidSettingValue;

/**
 * A fraction between 0.0 and 1.0 — `not_found.sample_rate`. Accepts a plain
 * int too: a JSON `1` decodes as a PHP int, not a float, and is a
 * legitimate "always sample" value.
 */
final class SampleRateSettingValidator implements SettingValueValidator
{
    public function validate(string $key, mixed $value): void
    {
        if (! is_int($value) && ! is_float($value)) {
            throw InvalidSettingValue::make($key, 'must be a number between 0.0 and 1.0.');
        }

        $rate = (float) $value;

        if ($rate < 0.0 || $rate > 1.0) {
            throw InvalidSettingValue::make($key, 'must be between 0.0 and 1.0.');
        }
    }
}
