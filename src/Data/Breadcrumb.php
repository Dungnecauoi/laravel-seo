<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

/**
 * One step in a breadcrumb trail.
 */
final class Breadcrumb
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $url = null,
    ) {
    }

    /**
     * Accepts the loose shapes people actually write breadcrumbs in.
     */
    public static function from(mixed $item): ?self
    {
        if ($item instanceof self) {
            return $item;
        }

        if (is_string($item)) {
            return new self($item);
        }

        if (! is_array($item)) {
            return null;
        }

        // ['Trang chủ' => '/'] — the shape most people reach for first.
        if (count($item) === 1 && ! isset($item['name'])) {
            $name = array_key_first($item);
            $url = reset($item);

            return is_string($name)
                ? new self($name, is_string($url) ? $url : null)
                : null;
        }

        $name = $item['name'] ?? $item['title'] ?? null;
        $url = $item['url'] ?? $item['href'] ?? $item['item'] ?? null;

        return is_string($name)
            ? new self($name, is_string($url) ? $url : null)
            : null;
    }
}
