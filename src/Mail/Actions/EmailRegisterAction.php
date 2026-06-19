<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Data\UserRegisteredData;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterUserRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\DataObject;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

final class EmailRegisterAction extends AbstractAction
{
    private string $modelClass;

    private ValidatorInstance $validator;

    protected function before(AbstractRecord $record): void
    {
        if (! $record instanceof EmailRegisterUserRecord) {
            throw new \InvalidArgumentException('Invalid record type');
        }

        $this->modelClass = $record->model_type;

        if (! class_exists($this->modelClass)) {
            throw new \InvalidArgumentException("Model {$this->modelClass} does not exist");
        }

        if (! in_array(MailAuthenticatable::class, class_implements($this->modelClass) ?: [], true)) {
            throw new \InvalidArgumentException("Model {$this->modelClass} must implement ".MailAuthenticatable::class);
        }

        $rules = $this->modelClass::getValidationRules();

        $this->validator = Validator::make($record->data->toArray(), $rules);

        if ($this->validator->fails()) {
            throw new ValidationException($this->validator);
        }
    }

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        $user = $this->modelClass::createUser($this->validator);

        return ResponseFactory::json(
            new UserRegisteredData(
                message: 'User registered successfully',
                user: DataObject::from($user->getOutputData()),
            ),
            201
        );
    }
}
