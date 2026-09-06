<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Settings;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Settings\SettingsRepository;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\DynamicSettingsController::update()},
 * one key at a time. `preview()` runs the same allowlist and value-shape
 * checks {@see SettingsRepository::set()} would, so a bad key or a value its
 * validator rejects fails on the propose call rather than only at confirm.
 * A secret key's own value is never echoed back in the preview text, the
 * same reason `GET` never echoes it either.
 */
final class SetSettingTool implements AiTool, AiToolPreviewable
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function name(): string
    {
        return 'seo.settings.set';
    }

    public function description(): string
    {
        return 'Override one dynamic setting. Only keys in seo.settings.keys are accepted, and each has its own value validation.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'key' => ['type' => 'string'],
                'value' => ['description' => 'Any JSON value — shape depends on the key.'],
            ],
            'required' => ['key', 'value'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Write;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        $key = (string) $input['key'];
        $value = $input['value'] ?? null;

        $this->settings->assertValid($key, $value);

        if ($this->settings->isSecret($key)) {
            return sprintf('Would set secret setting "%s" to a new value (not shown).', $key);
        }

        return sprintf('Would set "%s" to %s.', $key, json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $key = (string) $input['key'];

        $this->settings->set($key, $input['value'] ?? null);

        return ['key' => $key];
    }
}
