<?php

declare(strict_types=1);

namespace Duxbo\Seo\Mcp;

use Duxbo\Seo\Ai\Tools\AiToolDispatcher;
use Duxbo\Seo\Ai\Tools\AiToolRegistry;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Exceptions\AiToolNotFound;
use Duxbo\Seo\Exceptions\AiToolProposalExpired;
use Duxbo\Seo\Exceptions\AiToolUnauthorized;
use Duxbo\Seo\Exceptions\SeoException;
use Throwable;

/**
 * A hand-rolled, deliberately partial implementation of MCP (Model Context
 * Protocol) — JSON-RPC 2.0 over the non-streaming half of the Streamable
 * HTTP transport:
 * https://modelcontextprotocol.io/specification/2025-06-18/basic/transports
 *
 * Every {@see \Duxbo\Seo\Contracts\AiTool} already goes through
 * {@see AiToolRegistry}/{@see AiToolDispatcher}; this class only translates
 * between MCP's wire shape and that existing plumbing. No SSE, no
 * resources/prompts/sampling, no session management — nothing here needs
 * them, since no tool streams and every call is stateless, gated by the
 * same Gate abilities the REST API already uses. The spec explicitly allows
 * answering a JSON-RPC request with a single `application/json` object
 * instead of opening an event stream, which is the only case this
 * implements.
 *
 * MCP's `tools/call` has no native "confirm" step, so the propose/confirm
 * cycle rides inside `arguments`: a Write/Destructive tool's first call
 * returns a proposal (id and preview, in both the text content and
 * `structuredContent`); calling the same tool again with `arguments.confirm`
 * set to that id executes it. `confirm` is therefore a reserved argument
 * name no tool's own `inputSchema` should define.
 */
final class McpServer
{
    private const SERVER_VERSION = '1.0';

    /**
     * Versions this server has no version-specific behaviour to differ on —
     * everything here is `tools` only, so any of these is answered
     * identically. Listed anyway because the spec requires an explicit,
     * bounded negotiation rather than accepting anything a client sends.
     */
    private const SUPPORTED_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    private const DEFAULT_VERSION = '2025-06-18';

    public function __construct(
        private readonly AiToolRegistry $registry,
        private readonly AiToolDispatcher $dispatcher,
    ) {
    }

    public function supportsVersion(string $version): bool
    {
        return in_array($version, self::SUPPORTED_VERSIONS, true);
    }

    /**
     * @param  array<string, mixed>  $message  One decoded JSON-RPC message — the
     *                                          spec forbids batching multiple in one HTTP POST.
     * @return array{status: int, body: array<string, mixed>|null}
     */
    public function handle(array $message, AiToolContext $context): array
    {
        $id = $message['id'] ?? null;
        $method = $message['method'] ?? null;

        if (! is_string($method) || $method === '') {
            return $this->errorResponse($id, -32600, 'Invalid Request: "method" is required.');
        }

        // A notification (no "id") is never answered with a JSON-RPC
        // response body — just the transport-level 202 the spec requires.
        if ($id === null) {
            return ['status' => 202, 'body' => null];
        }

        if (! in_array($method, ['initialize', 'ping', 'tools/list', 'tools/call'], true)) {
            return $this->errorResponse($id, -32601, "Method not found: {$method}.");
        }

        /** @var array<string, mixed> $params */
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'ping' => (object) [],
                'tools/list' => $this->toolsList($context),
                'tools/call' => $this->toolsCall($params, $context),
            };
        } catch (AiToolNotFound $e) {
            return $this->errorResponse($id, -32602, $e->getMessage());
        } catch (AiToolUnauthorized $e) {
            return $this->errorResponse($id, -32000, $e->getMessage());
        } catch (AiToolProposalExpired $e) {
            return $this->errorResponse($id, -32001, $e->getMessage());
        } catch (SeoException $e) {
            // A business-logic refusal from inside a tool's own preview()/
            // execute() (an unsafe redirect, an invalid setting value, a
            // missing record) — a Tool Execution Error, not a protocol
            // error, per the spec's own error-handling split:
            // https://modelcontextprotocol.io/specification/2025-06-18/server/tools#error-handling
            return ['status' => 200, 'body' => $this->response($id, $this->toolError($e->getMessage()))];
        } catch (Throwable $e) {
            return $this->errorResponse($id, -32603, $e->getMessage());
        }

        return ['status' => 200, 'body' => $this->response($id, $result)];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;
        $version = is_string($requested) && $this->supportsVersion($requested) ? $requested : self::DEFAULT_VERSION;

        return [
            'protocolVersion' => $version,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'duxbo/laravel-seo', 'version' => self::SERVER_VERSION],
            'instructions' => 'Read tools run immediately. Write and Destructive tools return a proposal '
                .'first (id and preview, nothing changed yet); call the same tool again with '
                .'arguments.confirm set to that id to actually execute it.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolsList(AiToolContext $context): array
    {
        return [
            'tools' => array_map(
                fn (array $tool): array => $this->describeTool($tool),
                $this->registry->manifest($context),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $tool  One entry from {@see AiToolRegistry::manifest()}.
     * @return array<string, mixed>
     */
    private function describeTool(array $tool): array
    {
        $risk = AiToolRisk::from((string) $tool['risk_tier']);

        return [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'inputSchema' => $tool['input_schema'],
            // Informational only — the spec requires clients to treat tool
            // annotations as untrusted hints, never an access-control layer.
            'annotations' => [
                'readOnlyHint' => $risk === AiToolRisk::Read,
                'destructiveHint' => $risk === AiToolRisk::Destructive,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function toolsCall(array $params, AiToolContext $context): array
    {
        $name = (string) ($params['name'] ?? '');

        /** @var array<string, mixed> $arguments */
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        $confirm = isset($arguments['confirm']) ? (string) $arguments['confirm'] : null;
        unset($arguments['confirm']);

        $data = $this->dispatcher->call($name, $arguments, $context, $confirm)->toArray();

        return [
            'content' => [[
                'type' => 'text',
                'text' => (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]],
            'structuredContent' => $data,
            'isError' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolError(string $message): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $message]],
            'isError' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function response(int|string|null $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function errorResponse(int|string|null $id, int $code, string $message): array
    {
        return [
            'status' => 200,
            'body' => ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]],
        ];
    }
}
