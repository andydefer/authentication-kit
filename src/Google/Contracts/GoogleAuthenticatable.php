<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Contracts;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleIdTokenClaimsData;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for authenticatable entities that support the Google
 * Android ID-token-only flow.
 *
 * Implement this interface in addition to MailAuthenticatable if the
 * model can be resolved from verified Google claims. The service will
 * delegate user creation to `androidGenerate()` and will never set a
 * password, an email_verified_at timestamp, or any other field on its
 * own.
 */
interface GoogleAuthenticatable extends Authenticatable
{
    /**
     * Create a new authenticatable from verified Google ID-token claims.
     *
     * The implementation decides which fields to populate (name, email,
     * password, email_verified_at, oauth identity, ...). The service only
     * provides the verified claims.
     *
     * @param  GoogleIdTokenClaimsData  $claims  Verified Google ID-token claims.
     * @return Model&GoogleAuthenticatable The newly created model.
     */
    public static function androidGenerate(GoogleIdTokenClaimsData $claims): Model&GoogleAuthenticatable;
}
