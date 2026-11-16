<?php

namespace App\Support;

use App\Models\User;
use Closure;

/**
 * Other places a password can be checked, for the sign-in page's password form. Modules add
 * theirs (for example Access-Microsoft-AD checks Active Directory):
 *
 *     app(PasswordSignIn::class)->add('ad', fn (string $login, string $password): ?User => ..., enabled: fn () => ...);
 *
 * The checker gets what was typed (an email or a username) and returns the Tornado Engine
 * user, or null. Website passwords (Core's own) are tried first.
 */
class PasswordSignIn
{
    /** @var array<string, array{check: Closure, enabled: Closure}> */
    private array $checkers = [];

    public function add(string $key, Closure $check, Closure $enabled): static
    {
        $this->checkers[$key] = ['check' => $check, 'enabled' => $enabled];

        return $this;
    }

    /** Whether a module's password sign-in is on (so the form takes usernames too). */
    public function active(): bool
    {
        return (bool) array_filter($this->checkers, fn ($checker) => ($checker['enabled'])());
    }

    /** Whether the sign-in page shows the password form. */
    public function shown(): bool
    {
        return SignIn::enabled('password') || $this->active();
    }

    public function attempt(string $login, string $password): ?User
    {
        foreach ($this->checkers as $checker) {
            if (($checker['enabled'])() && ($user = ($checker['check'])($login, $password))) {
                return $user;
            }
        }

        return null;
    }
}
