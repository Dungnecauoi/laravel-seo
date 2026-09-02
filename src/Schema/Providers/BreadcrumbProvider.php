<?php

declare(strict_types=1);

namespace Duxbo\Seo\Schema\Providers;

use Duxbo\Seo\Contracts\SchemaProvider;
use Duxbo\Seo\Data\Breadcrumb;
use Duxbo\Seo\Data\SeoContext;

/**
 * The trail shown under a search result.
 *
 * Items come from the context bag, which a model fills via `seoBreadcrumbs()`
 * or a controller sets directly — breadcrumbs are a property of the route a
 * visitor took, which a model alone cannot always know.
 */
final class BreadcrumbProvider implements SchemaProvider
{
    public static function idFor(SeoContext $context): string
    {
        return $context->url.'#breadcrumb';
    }

    public function supports(SeoContext $context): bool
    {
        return $this->items($context) !== [];
    }

    public function id(SeoContext $context): string
    {
        return self::idFor($context);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(SeoContext $context): array
    {
        $elements = [];
        $position = 1;

        foreach ($this->items($context) as $crumb) {
            $element = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $crumb->name,
            ];

            // The last crumb is the current page and carries no item URL;
            // Google flags a self-referential final link as an error.
            if ($crumb->url !== null) {
                $element['item'] = $crumb->url;
            }

            $elements[] = $element;
        }

        // A trail of one is just the page itself and adds nothing.
        if (count($elements) < 2) {
            return [];
        }

        // Whatever the caller passed, the final crumb must not link.
        unset($elements[count($elements) - 1]['item']);

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    public function priority(): int
    {
        // Before WebPage, which references this node.
        return 40;
    }

    /**
     * @return list<Breadcrumb>
     */
    private function items(SeoContext $context): array
    {
        $items = $context->get('breadcrumbs', []);

        if (! is_array($items)) {
            return [];
        }

        $crumbs = [];

        foreach ($items as $item) {
            $crumb = Breadcrumb::from($item);

            if ($crumb !== null) {
                $crumbs[] = $crumb;
            }
        }

        return $crumbs;
    }
}
