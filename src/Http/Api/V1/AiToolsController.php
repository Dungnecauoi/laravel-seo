<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Ai\Tools\AiToolDispatcher;
use Duxbo\Seo\Ai\Tools\AiToolRegistry;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Exceptions\AiToolNotFound;
use Duxbo\Seo\Exceptions\AiToolProposalExpired;
use Duxbo\Seo\Exceptions\AiToolUnauthorized;
use Duxbo\Seo\Exceptions\SeoException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The REST surface over {@see AiToolRegistry}/{@see AiToolDispatcher} —
 * `GET` returns a manifest shaped so it can be used as-is as an OpenAI
 * `tools` array entry (`{name, description, parameters}`) or an Anthropic
 * tool-use entry (`{name, description, input_schema}`); both are the same
 * JSON Schema under a different key, so one manifest serves either.
 *
 * `POST .../call` takes `{input, confirm}` as two separate top-level
 * fields, unlike the MCP endpoint, which has only one `arguments` object
 * and so folds `confirm` into it — both ultimately reach the same
 * {@see AiToolDispatcher::call()}.
 */
final class AiToolsController extends ApiController
{
    public function __construct(
        private readonly AiToolRegistry $registry,
        private readonly AiToolDispatcher $dispatcher,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $manifest = $this->registry->manifest($this->context($request));

        return $this->json([
            'tools' => array_map(static fn (array $tool): array => $tool + [
                'parameters' => $tool['input_schema'],
            ], $manifest),
        ]);
    }

    public function call(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'input' => ['sometimes', 'array'],
            'confirm' => ['sometimes', 'nullable', 'string'],
        ]);

        try {
            $result = $this->dispatcher->call(
                $name,
                $validated['input'] ?? [],
                $this->context($request),
                $validated['confirm'] ?? null,
            );
        } catch (AiToolNotFound $e) {
            return $this->json(['message' => $e->getMessage()], 404);
        } catch (AiToolUnauthorized $e) {
            return $this->json(['message' => $e->getMessage()], 403);
        } catch (AiToolProposalExpired $e) {
            return $this->json(['message' => $e->getMessage()], 409);
        } catch (SeoException $e) {
            return $this->json(['message' => $e->getMessage()], 422);
        }

        return $this->json($result->toArray());
    }

    private function context(Request $request): AiToolContext
    {
        $locale = $request->query('locale');

        return new AiToolContext(
            user: $request->user(),
            locale: is_string($locale) ? $locale : null,
            transport: 'rest',
        );
    }
}
