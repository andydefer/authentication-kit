<?php

// src/Mail/Requests/GetCurrentUserRequest.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\AuthenticationKit\Mail\Enums\CurrentUserMode;
use AndyDefer\AuthenticationKit\Mail\Records\GetCurrentUserRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Illuminate\Validation\Rule;

final class GetCurrentUserRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['sometimes', 'string', Rule::in(CurrentUserMode::values())],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        $mode = $this->input('mode');

        return GetCurrentUserRecord::from([
            'mode' => $mode !== null
                ? CurrentUserMode::from($mode)
                : CurrentUserMode::SIMPLE,
        ]);
    }
}
