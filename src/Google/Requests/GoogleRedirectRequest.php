<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class GoogleRedirectRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [];
    }

    public function getRecord(): AbstractRecord
    {
        return GoogleRedirectRecord::from([
            'state' => (string) $this->input('state', ''),
            'code_challenge' => (string) $this->input('code_challenge', ''),
            'nonce' => (string) $this->input('nonce', ''),
        ]);
    }
}
