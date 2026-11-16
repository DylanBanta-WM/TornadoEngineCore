<?php

namespace App\Support;

use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * Socialite provider for Microsoft sign-in (Entra ID), limited to one tenant.
 */
class MicrosoftProvider extends AbstractProvider
{
    protected $scopes = ['openid', 'profile', 'email', 'User.Read'];

    protected $scopeSeparator = ' ';

    private function base(): string
    {
        return 'https://login.microsoftonline.com/'.rawurlencode((string) ($this->parameters['tenant'] ?? 'organizations')).'/oauth2/v2.0';
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->base().'/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->base().'/token';
    }

    protected function getCodeFields($state = null): array
    {
        return array_diff_key(parent::getCodeFields($state), ['tenant' => true]);
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get('https://graph.microsoft.com/v1.0/me', [
            RequestOptions::HEADERS => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'],
            RequestOptions::QUERY => ['$select' => 'id,displayName,mail,userPrincipalName'],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['id'] ?? null,
            'name' => $user['displayName'] ?? null,
            'email' => $user['mail'] ?? $user['userPrincipalName'] ?? null,
        ]);
    }
}
