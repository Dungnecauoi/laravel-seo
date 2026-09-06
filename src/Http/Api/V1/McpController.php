<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The single MCP endpoint — see {@see McpServer} for the actual protocol
 * handling. This class only does HTTP: reading the `MCP-Protocol-Version`
 * header, decoding the body, and shipping the response with the right
 * status code.
 */
final class McpController
{
    public function __construct(private readonly McpServer $server)
    {
    }

    public function __invoke(Request $request): JsonResponse|Response
    {
        $headerVersion = $request->header('MCP-Protocol-Version');

        if (is_string($headerVersion) && ! $this->server->supportsVersion($headerVersion)) {
            return new JsonResponse([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32600, 'message' => "Unsupported MCP-Protocol-Version: {$headerVersion}."],
            ], 400);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ($payload['jsonrpc'] ?? null) !== '2.0') {
            return new JsonResponse([
                'jsonrpc' => '2.0',
                'id' => is_array($payload) ? ($payload['id'] ?? null) : null,
                'error' => ['code' => -32600, 'message' => 'Invalid Request: expected a single JSON-RPC 2.0 message.'],
            ], 400);
        }

        $context = new AiToolContext(user: $request->user(), transport: 'mcp');

        ['status' => $status, 'body' => $body] = $this->server->handle($payload, $context);

        return $body === null ? response()->noContent($status) : new JsonResponse($body, $status);
    }

    /**
     * The spec allows GET (to open a server-initiated SSE stream) and
     * DELETE (to end a session) on the same endpoint — this server does
     * neither, and 405 is exactly how the spec says to say so.
     */
    public function notSupported(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'This endpoint only accepts POST. It does not support the server-initiated '
                .'SSE stream or explicit session termination.',
        ], 405);
    }
}
