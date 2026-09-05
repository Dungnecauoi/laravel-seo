<?php

declare(strict_types=1);

namespace Duxbo\Seo\Schema\Providers;

use Duxbo\Seo\Contracts\SchemaProvider;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\SeoContext;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * The publisher behind every page. Site-wide, so its `@id` is on the home URL
 * rather than the current page — every page's node points at the same one.
 */
final class OrganizationProvider implements SchemaProvider
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlGenerator $urls,
    ) {
    }

    public static function idFor(UrlGenerator $urls): string
    {
        return $urls->home().'#organization';
    }

    public function supports(SeoContext $context): bool
    {
        return is_string($this->config->get('seo.schema.organization.name'));
    }

    public function id(SeoContext $context): string
    {
        return self::idFor($this->urls);
    }

    /**
     * Config keys already spent on a specific field above or on the logo
     * node below — everything else in seo.schema.organization passes
     * through as-is, which is what lets a project add `geo`,
     * `foundingDate`, `areaServed`, `contactPoint`, or any other
     * schema.org property without this class needing to know its name in
     * advance. A fixed whitelist here would put this provider one field
     * short of whatever the next project actually needs, the same mistake
     * {@see \Duxbo\Seo\Schema\Types::product()} avoids with its own `$extra`.
     */
    private const HANDLED_KEYS = ['type', 'name', 'url', 'sameAs', 'logo', 'logo_width', 'logo_height'];

    /**
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public function build(SeoContext $context): array
    {
        /** @var array<string, mixed> $organization */
        $organization = $this->config->get('seo.schema.organization', []);

        $node = [
            '@type' => $organization['type'] ?? 'Organization',
            'name' => $organization['name'] ?? null,
            'url' => $organization['url'] ?? $this->urls->home(),
            'sameAs' => $organization['sameAs'] ?? [],
            ...array_diff_key($organization, array_flip(self::HANDLED_KEYS)),
        ];

        if (! isset($organization['logo']) || ! is_string($organization['logo'])) {
            return $node;
        }

        // The logo is its own top-level node rather than one nested inside the
        // organisation. Google wants dimensions on it, which a bare URL string
        // cannot carry, and both `logo` and `image` need to point at the same
        // thing — which only works if it has an identity of its own.
        $logoId = $this->urls->home().'#logo';

        $node['logo'] = ['@id' => $logoId];
        $node['image'] = ['@id' => $logoId];

        return [
            $node,
            array_filter([
                '@type' => 'ImageObject',
                '@id' => $logoId,
                'url' => $organization['logo'],
                'contentUrl' => $organization['logo'],
                'width' => $organization['logo_width'] ?? null,
                'height' => $organization['logo_height'] ?? null,
                'caption' => $organization['name'] ?? null,
            ], static fn (mixed $v): bool => $v !== null),
        ];
    }

    public function priority(): int
    {
        return 10;
    }
}
