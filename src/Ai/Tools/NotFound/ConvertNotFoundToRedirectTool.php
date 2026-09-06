<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\NotFound;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Contracts\RedirectMatcher;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Enums\RedirectType;
use Duxbo\Seo\Redirects\RedirectGuard;
use Duxbo\Seo\Redirects\RedirectRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\NotFoundController::redirect()}
 * — the quick "turn this 404 into a redirect" action, the whole reason a 404
 * monitor is more useful next to a redirect manager than alone.
 */
final class ConvertNotFoundToRedirectTool implements AiTool, AiToolPreviewable
{
    public function __construct(
        private readonly RedirectRepository $redirects,
        private readonly RedirectGuard $guard,
        private readonly RedirectMatcher $matcher,
        private readonly Config $config,
    ) {
    }

    public function name(): string
    {
        return 'seo.not_found.convert_to_redirect';
    }

    public function description(): string
    {
        return 'Create a 301 redirect from a logged 404 path to a new target, and remove it from the 404 log.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'The 404 log row id, from seo.not_found.list.'],
                'target' => ['type' => 'string'],
            ],
            'required' => ['id', 'target'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Write;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        $path = $this->pathFor((int) $input['id']);
        $target = (string) $input['target'];

        $this->guard->assertTargetIsSafe($target);
        $this->guard->assertNoLoop(
            $this->guard->normalise($path),
            $target,
            fn (string $p): ?string => $this->matcher->match($p)?->target,
        );

        return sprintf('Would create a 301 redirect from "%s" to "%s" and remove it from the 404 log.', $path, $target);
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $id = (int) $input['id'];
        $path = $this->pathFor($id);

        $redirect = $this->redirects->create($path, (string) $input['target'], RedirectType::MovedPermanently);

        DB::table($this->table())->where('id', $id)->delete();

        return ['id' => $redirect->getKey()];
    }

    private function pathFor(int $id): string
    {
        $row = DB::table($this->table())->find($id);

        if ($row === null) {
            throw new NotFoundHttpException("No 404 log entry with id [{$id}].");
        }

        return (string) $row->path;
    }

    private function table(): string
    {
        return (string) $this->config->get('seo.not_found.table', 'seo_not_found');
    }
}
