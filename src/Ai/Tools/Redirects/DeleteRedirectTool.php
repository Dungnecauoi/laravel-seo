<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Redirects;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Redirects\Redirect;
use Duxbo\Seo\Redirects\RedirectRepository;

final class DeleteRedirectTool implements AiTool, AiToolPreviewable
{
    public function __construct(private readonly RedirectRepository $redirects)
    {
    }

    public function name(): string
    {
        return 'seo.redirects.delete';
    }

    public function description(): string
    {
        return 'Permanently delete a redirect rule by id.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Destructive;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        $redirect = Redirect::query()->findOrFail((int) $input['id']);

        return sprintf(
            'Would permanently delete redirect #%d ("%s" \u{2192} "%s").',
            $redirect->getKey(),
            $redirect->source_path,
            $redirect->target ?? '(none)',
        );
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $id = (int) $input['id'];

        $this->redirects->deleteById($id);

        return ['id' => $id, 'deleted' => true];
    }
}
