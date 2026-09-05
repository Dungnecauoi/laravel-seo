<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Settings;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Settings\SettingsRepository;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\DynamicSettingsController::index()}
 * — same secret-masking rules, so a raw OAuth client secret or refresh
 * token is never handed to an AI caller any more than it is to a human one.
 */
final class GetSettingsTool implements AiTool
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function name(): string
    {
        return 'seo.settings.get';
    }

    public function description(): string
    {
        return 'List every dynamic setting this application allows overriding: its current value, the config default, and whether it is overridden. Secret keys report only whether a value is set.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Read;
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $data = [];

        foreach ($this->settings->allowedKeys() as $key) {
            $overridden = $this->settings->has($key);

            if ($this->settings->isSecret($key)) {
                $raw = $overridden ? $this->settings->get($key) : config("seo.{$key}");

                $data[$key] = [
                    'is_set' => is_string($raw) && $raw !== '',
                    'overridden' => $overridden,
                    'secret' => true,
                ];

                continue;
            }

            $default = config("seo.{$key}");

            $data[$key] = [
                'value' => $overridden ? $this->settings->get($key) : $default,
                'default' => $default,
                'overridden' => $overridden,
                'secret' => false,
            ];
        }

        return [
            'enabled' => $this->settings->enabled(),
            'settings' => $data,
        ];
    }
}
