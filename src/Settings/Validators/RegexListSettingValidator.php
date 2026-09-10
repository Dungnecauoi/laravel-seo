<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings\Validators;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Duxbo\Seo\Exceptions\InvalidSettingValue;
use Duxbo\Seo\Support\CatastrophicPattern;

/**
 * A list of pre-delimited PCRE patterns — `not_found.exclude`. Runs against
 * every 404, so a bad or hostile entry here is a live availability risk,
 * not just a shape mismatch — see {@see CatastrophicPattern}.
 */
final class RegexListSettingValidator implements SettingValueValidator
{
    public function validate(string $key, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            throw InvalidSettingValue::make($key, 'must be an array of regex patterns, or null.');
        }

        foreach ($value as $index => $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                throw InvalidSettingValue::make($key, sprintf('entry #%d must be a non-empty string.', $index));
            }

            if (CatastrophicPattern::detected($pattern)) {
                throw InvalidSettingValue::make($key, sprintf(
                    'entry #%d looks like it could hang on certain input (nested quantifier) and was rejected.',
                    $index,
                ));
            }

            if (@preg_match($pattern, '') === false) {
                throw InvalidSettingValue::make($key, sprintf('entry #%d is not a valid regular expression.', $index));
            }
        }
    }
}
