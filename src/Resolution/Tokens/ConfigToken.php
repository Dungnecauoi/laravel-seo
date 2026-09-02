<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution\Tokens;

use Duxbo\Seo\Contracts\TokenResolver;
use Duxbo\Seo\Data\SeoContext;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Reads a value from config — `%sitename%` and `%sep%`.
 */
final class ConfigToken implements TokenResolver
{
    public function __construct(
        private readonly string $key,
        private readonly string $configKey,
        private readonly Config $config,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function resolve(SeoContext $context, ?string $argument = null): ?string
    {
        $value = $this->config->get($this->configKey);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
