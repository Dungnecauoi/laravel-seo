<?php

declare(strict_types=1);

namespace Duxbo\Seo\Schema\Providers;

use Duxbo\Seo\Contracts\SchemaProvider;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\SeoContext;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * The site itself, and the sitelinks search box when a search URL is declared.
 */
final class WebSiteProvider implements SchemaProvider
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlGenerator $urls,
    ) {
    }

    public static function idFor(UrlGenerator $urls): string
    {
        return $urls->home().'#website';
    }

    public function supports(SeoContext $context): bool
    {
        return true;
    }

    public function id(SeoContext $context): string
    {
        return self::idFor($this->urls);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(SeoContext $context): array
    {
        $home = $this->urls->home();

        $node = [
            '@type' => 'WebSite',
            'url' => $home,
            'name' => $this->config->get('seo.site_name'),
            'publisher' => ['@id' => OrganizationProvider::idFor($this->urls)],
        ];

        if ($context->locale !== null) {
            $node['inLanguage'] = $context->locale;
        }

        $search = $this->config->get('seo.schema.website.search_url');

        if (is_string($search) && str_contains($search, '{search_term_string}')) {
            $node['potentialAction'] = [
                '@type' => 'SearchAction',
                // The template placeholder must survive normalisation, which is
                // why the URL is assembled here rather than passed as 'url'.
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $this->urls->absolute($search),
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $node;
    }

    public function priority(): int
    {
        return 20;
    }
}
