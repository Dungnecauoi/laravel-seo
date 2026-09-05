<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Exceptions\InvalidSettingValue;

/**
 * Checks the *value* of one dynamic setting write.
 *
 * {@see \Duxbo\Seo\Settings\SettingsRepository::set()} only ever checks that
 * the key is one the application chose to allowlist — never what the value
 * actually is, since a config key's shape is something only this package
 * knows. A validator registered per key in `seo.settings.validators` closes
 * that gap without the repository having to special-case each key itself.
 */
interface SettingValueValidator
{
    /**
     * @throws InvalidSettingValue
     */
    public function validate(string $key, mixed $value): void;
}
