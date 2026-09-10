<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings\Validators;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Duxbo\Seo\Exceptions\InvalidSettingValue;

/**
 * A whole number — `not_found.max_rows` and anything shaped like it.
 * Deliberately permits zero and negative values: `NotFoundLogger`'s own
 * row-limit enforcement already treats a limit `<= 0` as "uncapped," an
 * intentional value this validator must not reject before it gets there.
 */
final class IntegerSettingValidator implements SettingValueValidator
{
    public function validate(string $key, mixed $value): void
    {
        if (! is_int($value)) {
            throw InvalidSettingValue::make($key, 'must be a whole number.');
        }
    }
}
