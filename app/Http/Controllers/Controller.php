<?php

namespace App\Http\Controllers;

use App\Services\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

abstract class Controller
{
    protected function validationMessage(ValidationException $exception, string $fallback = 'Controlla i dati inviati.'): string
    {
        return collect($exception->errors())->flatten()->first() ?: $fallback;
    }

    protected function jsonValidationError(ValidationException $exception, string $fallback = 'Controlla i dati inviati.'): JsonResponse
    {
        return ApiResponse::json(
            $this->validationMessage($exception, $fallback),
            false,
            [],
            $exception->errors(),
            400,
        );
    }

    protected function jsonAuthorizationError(AuthorizationException $exception): JsonResponse
    {
        return ApiResponse::json($exception->getMessage() ?: 'Permessi insufficienti.', false, [], [], 403);
    }
}
