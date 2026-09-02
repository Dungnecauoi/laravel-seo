<?php

declare(strict_types=1);

namespace Duxbo\Seo\Schema;

use Duxbo\Seo\Data\SchemaGraph;

/**
 * Checks a graph against Google's required fields.
 *
 * Google does not report a missing required property — the rich result simply
 * never appears, with nothing anywhere to say why. Surfacing it locally is the
 * difference between a five-minute fix and a month of wondering.
 *
 * Only fields Google documents as required are listed. Recommended-but-optional
 * ones are left out: warning about every one of those trains people to ignore
 * the output.
 */
final class SchemaValidator
{
    /**
     * Type => required property names.
     *
     * @var array<string, list<string>>
     */
    private const REQUIRED = [
        'Article' => ['headline'],
        'BlogPosting' => ['headline'],
        'NewsArticle' => ['headline'],
        'Product' => ['name'],
        'Offer' => ['price', 'priceCurrency'],
        'AggregateRating' => ['ratingValue', 'ratingCount'],
        'Review' => ['author', 'reviewRating'],
        'Rating' => ['ratingValue'],
        'FAQPage' => ['mainEntity'],
        'Question' => ['name', 'acceptedAnswer'],
        'Answer' => ['text'],
        'BreadcrumbList' => ['itemListElement'],
        'ListItem' => ['position', 'name'],
        'VideoObject' => ['name', 'thumbnailUrl', 'uploadDate'],
        'Event' => ['name', 'startDate', 'location'],
        'JobPosting' => ['title', 'description', 'datePosted', 'hiringOrganization', 'jobLocation'],
        'Recipe' => ['name', 'image', 'recipeIngredient', 'recipeInstructions'],
        'HowTo' => ['name', 'step'],
        'Course' => ['name', 'description', 'provider'],
        'Organization' => ['name'],
        'LocalBusiness' => ['name', 'address'],
        'Person' => ['name'],
        'ImageObject' => ['url'],
        'WebSite' => ['url'],
        'WebPage' => ['url'],
        'SoftwareApplication' => ['name', 'offers', 'aggregateRating'],
    ];

    /**
     * @return list<string> Human-readable problems; empty when the graph is sound.
     */
    public function validate(SchemaGraph $graph): array
    {
        $problems = [];

        foreach ($graph->nodes() as $id => $node) {
            foreach ($this->missingIn($node) as $problem) {
                $problems[] = "{$id}: {$problem}";
            }
        }

        foreach ($graph->danglingReferences() as $dangling) {
            $problems[] = "reference to [{$dangling}], which is not in the graph";
        }

        return $problems;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return list<string>
     */
    private function missingIn(array $node): array
    {
        $problems = [];
        $type = $node['@type'] ?? null;

        if (is_string($type) && isset(self::REQUIRED[$type])) {
            foreach (self::REQUIRED[$type] as $property) {
                if (! isset($node[$property]) || $node[$property] === '' || $node[$property] === []) {
                    $problems[] = "{$type} is missing required property [{$property}]";
                }
            }
        }

        // Nested nodes carry requirements of their own — an Offer inside a
        // Product is where price errors actually live.
        foreach ($node as $value) {
            if (! is_array($value)) {
                continue;
            }

            if (isset($value['@type'])) {
                $problems = [...$problems, ...$this->missingIn($value)];

                continue;
            }

            foreach ($value as $item) {
                if (is_array($item) && isset($item['@type'])) {
                    $problems = [...$problems, ...$this->missingIn($item)];
                }
            }
        }

        return $problems;
    }
}
