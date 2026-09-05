<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Contracts\SettingValueValidator;
use Duxbo\Seo\Tests\TestCase;

/**
 * The bug this guards against: a key gets added to `seo.settings.keys` (this
 * package's own, or a project's own additions) and nobody notices its value
 * is never actually checked — exactly what `AiBudget::assertWithinBudget()`
 * quietly relied on for `ai.daily_token_budget` before it had a validator.
 * A missing entry here is a build failure, not a runtime surprise.
 */
final class SettingsAllowlistHasValidatorsTest extends TestCase
{
    public function test_every_allowlisted_key_has_a_registered_validator(): void
    {
        $keys = config('seo.settings.keys', []);
        $validators = config('seo.settings.validators', []);

        $missing = array_values(array_diff($keys, array_keys($validators)));

        $this->assertSame([], $missing, sprintf(
            'These seo.settings.keys entries have no validator in seo.settings.validators: %s',
            implode(', ', $missing),
        ));
    }

    public function test_every_registered_validator_class_implements_the_contract(): void
    {
        $validators = config('seo.settings.validators', []);

        foreach ($validators as $key => $class) {
            $this->assertTrue(
                is_a($class, SettingValueValidator::class, true),
                sprintf('Validator for [%s] (%s) does not implement SettingValueValidator.', $key, $class),
            );
        }
    }
}
