<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class GoogleCallbackRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required_without:error', 'string'],
            'state' => ['required', 'string'],
            'error' => ['nullable', 'string'],
            'error_description' => ['nullable', 'string'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return GoogleCallbackRecord::from([
            'code' => (string) $this->input('code', ''),
            'state' => (string) $this->input('state', ''),
            'error' => $this->input('error'),
            'error_description' => $this->input('error_description'),
        ]);
    }
}
