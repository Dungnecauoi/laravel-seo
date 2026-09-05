<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings\Validators;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Duxbo\Seo\Enums\TwitterCard;
use Duxbo\Seo\Exceptions\InvalidSettingValue;

/**
 * One of the four `twitter:card` values the spec actually defines — an
 * arbitrary string here would sit silently unused, since every consumer
 * reads it through `TwitterCard::tryFrom()` and drops anything unknown.
 */
final class TwitterCardSettingValidator implements SettingValueValidator
{
    public function validate(string $key, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || TwitterCard::tryFrom($value) === null) {
            throw InvalidSettingValue::make($key, sprintf(
                'must be one of: %s.',
                implode(', ', array_map(static fn (TwitterCard $case): string => $case->value, TwitterCard::cases())),
            ));
        }
    }
}
