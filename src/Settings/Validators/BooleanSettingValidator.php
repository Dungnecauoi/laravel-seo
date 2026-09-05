<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings\Validators;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Duxbo\Seo\Exceptions\InvalidSettingValue;

/**
 * A toggle: `true` or `false`, nothing looser. A string `"false"` would
 * decode from JSON as truthy in most `=== true` comparisons this package
 * uses throughout `config('seo.*')` reads, silently switching a feature on.
 */
final class BooleanSettingValidator implements SettingValueValidator
{
    public function validate(string $key, mixed $value): void
    {
        if (! is_bool($value)) {
            throw InvalidSettingValue::make($key, 'must be true or false.');
        }
    }
}
