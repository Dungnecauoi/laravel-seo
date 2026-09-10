<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings\Validators;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Duxbo\Seo\Exceptions\InvalidSettingValue;

/**
 * A list of bare hostnames — `redirects.allowed_hosts`. Deliberately
 * distinct from {@see UrlListSettingValidator}: {@see \Duxbo\Seo\Support\SameOriginUrls::allowedHosts()}
 * compares this list directly against `parse_url($target, PHP_URL_HOST)`,
 * so a full `"https://cdn.example.com"` entry here would never match.
 */
final class HostListSettingValidator implements SettingValueValidator
{
    private const PATTERN = '/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))*$/';

    public function validate(string $key, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            throw InvalidSettingValue::make($key, 'must be an array of bare hostnames, or null.');
        }

        foreach ($value as $index => $host) {
            if (! is_string($host) || preg_match(self::PATTERN, $host) !== 1) {
                throw InvalidSettingValue::make($key, sprintf(
                    'entry #%d must be a bare hostname (e.g. "cdn.example.com"), not a full URL.',
                    $index,
                ));
            }
        }
    }
}
