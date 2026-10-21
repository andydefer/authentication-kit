<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class GoogleAndroidCallbackRequest extends AbstractRequest
{
    /**
     * {@inheritDoc}
     */
    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string'],
            'id_token' => ['required', 'string'],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getRecord(): AbstractRecord
    {
        return GoogleAndroidCallbackRecord::from([
            'model_type' => $this->input('model_type'),
            'id_token' => $this->input('id_token'),
        ]);
    }
}
