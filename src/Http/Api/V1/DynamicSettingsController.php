<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Exceptions\InvalidSettingValue;
use Duxbo\Seo\Exceptions\UnknownSetting;
use Duxbo\Seo\Settings\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The JSON surface a project builds its own settings page on — this
 * package ships no UI for these, since the whole point of a REST API here
 * is that whichever front end (React, Vue, a completely custom admin) reads
 * `GET` to render a form and calls `PUT` to save it, without either needing
 * to know the other exists.
 */
final class DynamicSettingsController extends ApiController
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function index(): JsonResponse
    {
        $keys = $this->settings->allowedKeys();
        $data = [];

        foreach ($keys as $key) {
            $overridden = $this->settings->has($key);

            if ($this->settings->isSecret($key)) {
                // Never the value, not even the one still sitting in
                // config/seo.php — a client secret or refresh token is
                // exactly as sensitive whether it came from the file or a
                // stored override, and neither has a safe partial-reveal
                // convention the way a card number's last four digits does.
                $raw = $overridden ? $this->settings->get($key) : config("seo.{$key}");

                $data[$key] = [
                    'is_set' => is_string($raw) && $raw !== '',
                    'overridden' => $overridden,
                    'secret' => true,
                ];

                continue;
            }

            $default = config("seo.{$key}");

            $data[$key] = [
                'value' => $overridden ? $this->settings->get($key) : $default,
                'default' => $default,
                'overridden' => $overridden,
                'secret' => false,
            ];
        }

        return $this->json([
            'enabled' => $this->settings->enabled(),
            'settings' => $data,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
        ]);

        /** @var array<string, mixed> $settings */
        $settings = $validated['settings'];

        // Validated as a whole before anything is written: a request naming
        // one bad key (unknown, or a shape its validator rejects) alongside
        // nine good ones must not save nine of them and then fail on the
        // tenth, leaving the caller unsure what stuck.
        foreach ($settings as $key => $value) {
            try {
                $this->settings->assertValid((string) $key, $value);
            } catch (UnknownSetting|InvalidSettingValue $e) {
                return $this->json(['message' => $e->getMessage()], 422);
            }
        }

        foreach ($settings as $key => $value) {
            $this->settings->set((string) $key, $value);
        }

        return $this->json(['saved' => array_keys($settings)]);
    }

    public function destroy(string $key): JsonResponse
    {
        try {
            $this->settings->forget($key);
        } catch (UnknownSetting $e) {
            return $this->json(['message' => $e->getMessage()], 422);
        }

        return $this->json(['cleared' => $key]);
    }
}
