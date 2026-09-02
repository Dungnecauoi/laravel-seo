<?php

declare(strict_types=1);

namespace Duxbo\Seo\Formatters;

use Duxbo\Seo\Contracts\OutputFormatter;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Schema\GraphAssembler;
use Illuminate\Support\HtmlString;

/**
 * The `<script type="application/ld+json">` block.
 */
final class JsonLdFormatter implements OutputFormatter
{
    public function __construct(private readonly GraphAssembler $assembler)
    {
    }

    public function name(): string
    {
        return 'jsonld';
    }

    public function format(SeoContext $context): HtmlString
    {
        $graph = $this->assembler->build($context);

        if ($graph->isEmpty()) {
            return new HtmlString('');
        }

        // HEX_TAG is the one that matters: it escapes < and > so a "</script>"
        // inside a description cannot close the block and turn the rest of the
        // JSON into executable markup.
        $json = json_encode(
            $graph->toArray(),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT,
        );

        if ($json === false) {
            return new HtmlString('');
        }

        return new HtmlString('<script type="application/ld+json">'.$json.'</script>');
    }
}
