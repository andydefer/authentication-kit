<?php

// src/Mail/Utils/AuthenticationResolver.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Utils;

use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Utility class for resolving authentication service and authenticatable model.
 *
 * This class provides methods to resolve the mail authentication service
 * and the authenticatable model from a given model type and identifier.
 */
final class AuthenticationResolver
{
    /**
     * Resolve the mail authentication service for a given model type.
     *
     * @param  string  $modelType  The fully qualified class name of the model
     * @return MailAuthenticationService|null The mail authentication service, or null if invalid
     */
    public static function resolveService(string $modelType): ?MailAuthenticationService
    {
        if (! self::isValidAuthenticatable($modelType)) {
            return null;
        }

        /** @var string $modelClass */
        $modelClass = $modelType;

        return $modelClass::getMailAuthService();
    }

    /**
     * Resolve the authenticatable model for a given model type and ID.
     *
     * @param  string  $modelType  The fully qualified class name of the model
     * @param  int|string  $id  The ID of the model
     * @param  bool  $includeTrashed  Whether to include soft-deleted records
     * @return Model|null The authenticatable model, or null if not found
     */
    public static function resolveAuthenticatable(string $modelType, int|string $id, bool $includeTrashed = false): ?Model
    {
        if (! self::isValidAuthenticatable($modelType)) {
            return null;
        }

        /** @var string $modelClass */
        $modelClass = $modelType;

        $query = $modelClass::query();

        if ($includeTrashed && self::usesSoftDeletes($modelType)) {
            $query = $query->withTrashed();
        }

        $authenticatable = $query->find($id);

        // Vérifier si l'utilisateur est soft-deleted
        if ($authenticatable !== null && self::usesSoftDeletes($modelType) && $authenticatable->trashed()) {
            return null;
        }

        return $authenticatable;
    }

    /**
     * Resolve the authenticatable model by email.
     *
     * @param  string  $modelType  The fully qualified class name of the model
     * @param  string  $email  The email address
     * @param  bool  $includeTrashed  Whether to include soft-deleted records
     * @return Model|null The authenticatable model, or null if not found
     */
    public static function resolveAuthenticatableByEmail(string $modelType, string $email, bool $includeTrashed = false): ?Model
    {
        if (! self::isValidAuthenticatable($modelType)) {
            return null;
        }

        /** @var string $modelClass */
        $modelClass = $modelType;

        $query = $modelClass::query();

        if ($includeTrashed && self::usesSoftDeletes($modelType)) {
            $query = $query->withTrashed();
        }

        $authenticatable = $query->where('email', strtolower(trim($email)))->first();

        // Vérifier si l'utilisateur est soft-deleted
        if ($authenticatable !== null && self::usesSoftDeletes($modelType) && $authenticatable->trashed()) {
            return null;
        }

        return $authenticatable;
    }

    /**
     * Check if a model type is a valid MailAuthenticatable.
     *
     * @param  string  $modelType  The fully qualified class name of the model
     * @return bool True if the model type is valid
     */
    public static function isValidAuthenticatable(string $modelType): bool
    {
        if (! class_exists($modelType)) {
            return false;
        }

        if (! is_subclass_of($modelType, Model::class)) {
            return false;
        }

        $implements = class_implements($modelType);

        return in_array(MailAuthenticatable::class, $implements, true);
    }

    /**
     * Check if a model type uses SoftDeletes.
     *
     * @param  string  $modelType  The fully qualified class name of the model
     * @return bool True if the model uses SoftDeletes
     */
    public static function usesSoftDeletes(string $modelType): bool
    {
        if (! class_exists($modelType)) {
            return false;
        }

        return in_array(SoftDeletes::class, class_uses($modelType), true);
    }

    /**
     * Resolve both the service and the authenticatable model.
     *
     * @param  string  $modelType  The fully qualified class name of the model
     * @param  int|string  $id  The ID of the model
     * @param  bool  $includeTrashed  Whether to include soft-deleted records
     * @return array{service: MailAuthenticationService|null, authenticatable: Model|null}
     */
    public static function resolve(string $modelType, int|string $id, bool $includeTrashed = false): array
    {
        return [
            'service' => self::resolveService($modelType),
            'authenticatable' => self::resolveAuthenticatable($modelType, $id, $includeTrashed),
        ];
    }

    /**
     * Resolve both the service and the authenticatable model by email.
     *
     * @param  string  $modelType  The fully qualified class name of the model
     * @param  string  $email  The email address
     * @param  bool  $includeTrashed  Whether to include soft-deleted records
     * @return array{service: MailAuthenticationService|null, authenticatable: Model|null}
     */
    public static function resolveByEmail(string $modelType, string $email, bool $includeTrashed = false): array
    {
        return [
            'service' => self::resolveService($modelType),
            'authenticatable' => self::resolveAuthenticatableByEmail($modelType, $email, $includeTrashed),
        ];
    }
}
