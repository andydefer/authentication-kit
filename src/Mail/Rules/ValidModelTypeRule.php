<?php

// src/Mail/Rules/ValidModelTypeRule.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Rules;

use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Validation rule that ensures the model_type exists and implements MailAuthenticatable.
 *
 * This rule validates that:
 * 1. The class exists
 * 2. The class implements MailAuthenticatable
 * 3. The class is a valid Eloquent model
 */
final class ValidModelTypeRule implements ValidationRule
{
    private ?string $modelClass = null;

    private ?string $errorMessage = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The model type must be a string.');

            return;
        }

        // 1. Vérifier que la classe existe
        if (! class_exists($value)) {
            $fail("The model class '{$value}' does not exist.");

            return;
        }

        // 2. Vérifier que c'est un modèle Eloquent
        if (! is_subclass_of($value, Model::class)) {
            $fail("The model class '{$value}' must be an Eloquent model.");

            return;
        }

        // 3. Vérifier que le modèle implémente MailAuthenticatable
        $implements = class_implements($value);
        if (! in_array(MailAuthenticatable::class, $implements, true)) {
            $fail("The model class '{$value}' must implement ".MailAuthenticatable::class);

            return;
        }

        $this->modelClass = $value;
    }

    /**
     * Get the validated model class.
     */
    public function getModelClass(): ?string
    {
        return $this->modelClass;
    }
}
