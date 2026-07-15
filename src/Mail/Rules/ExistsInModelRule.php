<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

final class ExistsInModelRule implements ValidationRule
{
    public function __construct(
        private readonly string $modelClass,
        private readonly string $column = 'email',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! class_exists($this->modelClass)) {
            $fail('The specified model type does not exist.');

            return;
        }

        if (! is_subclass_of($this->modelClass, Model::class)) {
            $fail('The specified model type must be an Eloquent model.');

            return;
        }

        $modelClass = $this->modelClass;

        $exists = $modelClass::where($this->column, $value)->exists();

        if (! $exists) {
            $fail('No user found with this email address.');
        }
    }
}
