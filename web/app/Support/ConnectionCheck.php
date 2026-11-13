<?php

namespace App\Support;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks settings work before they're saved. Each method returns null when they do,
 * or a message saying what's wrong.
 */
class ConnectionCheck
{
    /**
     * Asks Google to swap a made-up sign-in code using this client. Google says "invalid_grant"
     * (the code is wrong) only once it has accepted the client ID and secret.
     */
    public function google(string $clientId, string $clientSecret, string $redirectUri): ?string
    {
        try {
            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'authorization_code',
                'code' => 'tornado-engine-check',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
            ]);
        } catch (ConnectionException) {
            return __('Couldn\'t reach Google to check the client ID and secret.');
        }

        return match ($response->json('error')) {
            'invalid_grant' => null,
            'invalid_client', 'unauthorized_client' => __('Google didn\'t accept this client ID and secret.'),
            'redirect_uri_mismatch' => __('Add :uri to the client\'s authorized redirect URIs in Google Cloud Console.', ['uri' => $redirectUri]),
            default => __('Google couldn\'t check this client ID and secret.'),
        };
    }

    /**
     * Lists one model with the login, which costs nothing.
     *
     * @param  string  $type  "subscription" (a token from claude setup-token) or "api_key"
     */
    public function claude(string $type, string $credential): ?string
    {
        try {
            $client = $type === 'api_key' ? new Client(apiKey: $credential) : new Client(authToken: $credential);
            $client->models->list(limit: 1, requestOptions: [
                'timeout' => 15.0,
                'maxRetries' => 0,
                'extraHeaders' => $type === 'subscription' ? ['anthropic-beta' => 'oauth-2025-04-20'] : [],
            ]);
        } catch (AuthenticationException|PermissionDeniedException) {
            return $type === 'api_key'
                ? __('Anthropic didn\'t accept this API key.')
                : __('Anthropic didn\'t accept this token. Make a new one with claude setup-token.');
        } catch (APIConnectionException) {
            return __('Couldn\'t reach Anthropic to check the login.');
        } catch (Throwable $e) {
            report($e);

            return __('Couldn\'t check the login with Anthropic.');
        }

        return null;
    }
}
