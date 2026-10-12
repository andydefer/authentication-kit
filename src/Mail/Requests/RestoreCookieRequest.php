<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Records\RestoreCookieAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Rules\ValidModelTypeRule;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Validates the payload used to restore a missing auth cookie.
 *
 * The route is public: the client still holds the plain token in its own
 * storage but its cookie has been dropped by the browser. The plain token
 * is verified internally before the cookie is written back.
 */
final class RestoreCookieRequest extends AbstractRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string', new ValidModelTypeRule],
            'token' => ['required', 'string', 'min:16'],
            'remember_me' => ['sometimes', 'boolean'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return new RestoreCookieAuthRecord(
            model_type: $this->input('model_type'),
            token: $this->input('token'),
            remember_me: $this->input('remember_me', false),
            ip: $this->ip(),
            user_agent: $this->userAgent(),
        );
    }
}
