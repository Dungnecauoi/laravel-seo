<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Settings;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Exceptions\UnknownSetting;
use Duxbo\Seo\Settings\SettingsRepository;

/**
 * Reverts one dynamic setting to whatever `config/seo.php` (or the
 * environment) itself says — tiered Destructive by convention, since the
 * stored override is gone the moment this runs and {@see SettingsRepository::forget()}'s
 * own docblock is explicit that nothing keeps the old value around to
 * restore.
 */
final class ClearSettingTool implements AiTool, AiToolPreviewable
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function name(): string
    {
        return 'seo.settings.clear';
    }

    public function description(): string
    {
        return 'Remove a stored override for one dynamic setting, reverting it to the config file\'s own value.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['key' => ['type' => 'string']],
            'required' => ['key'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Destructive;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        $key = (string) $input['key'];

        if (! in_array($key, $this->settings->allowedKeys(), true)) {
            throw UnknownSetting::named($key, $this->settings->allowedKeys());
        }

        return sprintf('Would clear the override for "%s", reverting to the config file\'s value.', $key);
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $key = (string) $input['key'];

        $this->settings->forget($key);

        return ['key' => $key];
    }
}
