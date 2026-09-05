<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Duxbo\Seo\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Saves the form {@see SettingsController} renders below the read-only
 * status tables — the session-authenticated twin of
 * {@see \Duxbo\Seo\Http\Api\V1\DynamicSettingsController}, over the same
 * `SettingsRepository` and the same allowlist.
 */
final class DynamicSettingsController
{
    /** @var list<string> */
    private const BOOLEAN_KEYS = [
        'enabled',
        'robots.block_ai_crawlers',
        'indexnow.enabled',
        'search_console.enabled',
    ];

    /** @var list<string> */
    private const ARRAY_KEYS = [
        'schema.organization.sameAs',
    ];

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->settings->enabled(), 404);

        foreach ($this->settings->allowedKeys() as $key) {
            $field = self::fieldName($key);

            if (in_array($key, self::BOOLEAN_KEYS, true)) {
                $this->settings->set($key, $request->boolean($field));

                continue;
            }

            if ($this->settings->isSecret($key)) {
                // Blank means "leave it" — a secret's own current value is
                // never sent back to the form to be resubmitted unchanged,
                // so there is nothing to compare an edit against.
                $value = $request->input($field);

                if (is_string($value) && $value !== '') {
                    $this->settings->set($key, $value);
                }

                continue;
            }

            if (in_array($key, self::ARRAY_KEYS, true)) {
                $lines = array_values(array_filter(array_map(
                    'trim',
                    explode("\n", (string) $request->input($field, '')),
                )));

                $lines === [] ? $this->settings->forget($key) : $this->settings->set($key, $lines);

                continue;
            }

            $value = $request->input($field);

            // An emptied field reverts to config/seo.php's own value rather
            // than storing an override that is itself blank — the same
            // "clear the box to go back to the default" a caller gets by
            // calling DELETE on the API instead of PUT with an empty string.
            is_string($value) && $value !== '' ? $this->settings->set($key, $value) : $this->settings->forget($key);
        }

        return back()->with('seo_status', 'Đã lưu cấu hình.');
    }

    public static function fieldName(string $key): string
    {
        return str_replace('.', '__', $key);
    }
}
