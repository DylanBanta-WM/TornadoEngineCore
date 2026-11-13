<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The settings from first-time setup, used by setup and by Administration.
 */
class Settings
{
    /** @return array<string, array<int, mixed>> */
    public static function addressRules(): array
    {
        return [
            'address' => ['required', 'string', 'max:255', 'url:https', function ($attribute, $value, $fail) {
                $host = parse_url($value, PHP_URL_HOST) ?: '';

                if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) || ! str_contains($host, '.')) {
                    $fail(__('Use a domain name, not an IP address.'));
                }
            }],
        ];
    }

    /**
     * Saves the web address and returns it as stored, e.g. https://te.example.org.
     */
    public static function saveAddress(string $input): string
    {
        $parts = parse_url($input);
        $port = isset($parts['port']) && $parts['port'] !== 443 ? ':'.$parts['port'] : '';
        $address = 'https://'.strtolower($parts['host']).$port;

        Setting::put('site.url', $address);

        return $address;
    }

    /** @return array<string, array<int, string>> */
    public static function googleRules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+(\.[a-z0-9-]+)+$/i'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
        ];
    }

    /** @param  array{domain: string, client_id: string, client_secret: string}  $data */
    public static function saveGoogle(array $data): void
    {
        Setting::put('google.domain', strtolower(trim($data['domain'])));
        Setting::put('google.client_id', trim($data['client_id']));
        Setting::putSecret('google.client_secret', trim($data['client_secret']));
    }

    /**
     * Makes a new harness token (the old one stops working) and returns it.
     */
    public static function newHarnessToken(): string
    {
        $token = Str::random(48);
        Setting::put('harness.token_hash', hash('sha256', $token));
        Setting::put('harness.last_seen_at', null);

        return $token;
    }

    /** @return array<string, array<int, mixed>> */
    public static function claudeRules(): array
    {
        return [
            'type' => ['required', Rule::in(['subscription', 'api_key'])],
            'credential' => ['required', 'string', 'max:500'],
        ];
    }

    /** @param  array{type: string, credential: string}  $data */
    public static function saveClaude(array $data): void
    {
        Setting::put('harness.claude_credential_type', $data['type']);
        Setting::putSecret('harness.claude_credential', trim($data['credential']));
    }
}
