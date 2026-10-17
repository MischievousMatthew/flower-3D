<?php

namespace Tests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Signs a user or employee in the way the app does since the switch from
 * Sanctum to its own tokens: a SHA-256 hash in `api_token`, checked by the
 * TokenAuth middleware against the request's Bearer token.
 */
trait SignsInWithApiToken
{
    protected function signIn(Model $account): static
    {
        $plain = Str::random(60);
        $account->forceFill(['api_token' => hash('sha256', $plain)])->save();

        return $this->withToken($plain);
    }
}
