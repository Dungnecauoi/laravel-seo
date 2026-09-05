<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings\Validators;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Duxbo\Seo\Exceptions\InvalidSettingValue;

/**
 * A list of profile URLs — `schema.organization.sameAs` and anything shaped
 * like it. `null` or `[]` both mean "no profiles."
 */
final class UrlListSettingValidator implements SettingValueValidator
{
    public function validate(string $key, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            throw InvalidSettingValue::make($key, 'must be an array of URLs, or null.');
        }

        $url = new UrlSettingValidator();

        foreach ($value as $index => $entry) {
            if ($entry === null) {
                throw InvalidSettingValue::make($key, sprintf('entry #%d must be a URL, not null.', $index));
            }

            $url->validate($key, $entry);
        }
    }
}
