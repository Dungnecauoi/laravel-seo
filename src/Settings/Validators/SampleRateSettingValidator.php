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

        // is_nan() is checked explicitly because NAN fails both bound
        // comparisons below (any comparison against NAN is false under
        // IEEE-754), so a naive range check alone would let it through —
        // not reachable through the JSON-decoded write path this
        // validator is actually wired to today (JSON has no NaN literal),
        // but this validator has no way to know every future caller will
        // route through JSON.
        if (is_nan($rate) || $rate < 0.0 || $rate > 1.0) {
            throw InvalidSettingValue::make($key, 'must be between 0.0 and 1.0.');
        }
    }
}
