<?php

declare(strict_types=1);

namespace Duxbo\Seo\Settings\Validators;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Duxbo\Seo\Exceptions\InvalidSettingValue;

/**
 * An absolute `http(s)` URL — an organization logo, a search action
 * template, a site URL. `javascript:` and friends are rejected the same
 * reason {@see \Duxbo\Seo\Redirects\RedirectGuard} refuses anything that
 * isn't a path or an approved host: this value is emitted straight into
 * page markup, and this package has no other point where a scheme is
 * checked on the way in.
 */
final class UrlSettingValidator implements SettingValueValidator
{
    private const MAX_LENGTH = 2048;

    public function validate(string $key, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || $value === '') {
            throw InvalidSettingValue::make($key, 'must be a non-empty URL string, or null.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidSettingValue::make($key, sprintf('must be %d characters or fewer.', self::MAX_LENGTH));
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw InvalidSettingValue::make($key, 'must be an absolute http:// or https:// URL.');
        }
    }
}
