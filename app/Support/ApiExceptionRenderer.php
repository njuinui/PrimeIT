<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): JsonResponse
    {
        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : null;

        $status = match (true) {
            $e instanceof ValidationException           => 422,
            $e instanceof AuthenticationException       => 401,
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException,
            $statusCode === 403                        => 403,
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException,
            $statusCode === 404                        => 404,
            $e instanceof TooManyRequestsHttpException,
            $statusCode === 429                        => 429,
            default                                    => 500,
        };

        $message = match (true) {
            $e instanceof ValidationException           => 'The given data was invalid.',
            $e instanceof AuthenticationException       => 'Unauthenticated.',
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException,
            $statusCode === 403                        => 'This action is unauthorized.',
            $e instanceof ModelNotFoundException        => 'Resource not found.',
            $e instanceof NotFoundHttpException,
            $statusCode === 404                        => 'Not found.',
            $e instanceof TooManyRequestsHttpException,
            $statusCode === 429                        => 'Too many requests. Please slow down.',
            default => config('app.debug')
                ? $e->getMessage()
                : 'Server error. Please try again later.',
        };

        $body = ['success' => false, 'message' => $message];

        if ($e instanceof ValidationException) {
            $body['errors'] = $e->errors();
        }

        if (config('app.debug')) {
            $body['debug'] = [
                'exception' => get_class($e),
                'file'      => $e->getFile().':'.$e->getLine(),
            ];
        }

        return response()->json($body, $status);
    }
}