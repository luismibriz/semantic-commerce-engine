<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Domain\Exception\ProductNotFound;
use App\Catalog\Infrastructure\Embedding\EmbeddingFailed;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Translates exceptions raised under /api into consistent JSON error
 * responses. Domain validation errors are client errors (400); embedding
 * provider failures are upstream errors (502); nothing else leaks details.
 */
#[AsEventListener(event: 'kernel.exception')]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();
        [$status, $message] = $this->resolve($throwable);

        $event->setResponse(new JsonResponse(['error' => $message], $status));
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function resolve(Throwable $throwable): array
    {
        return match (true) {
            $throwable instanceof ProductNotFound => [Response::HTTP_NOT_FOUND, $throwable->getMessage()],

            $throwable instanceof InvalidArgumentException,
            $throwable instanceof JsonException => [Response::HTTP_BAD_REQUEST, $throwable->getMessage()],

            $throwable instanceof EmbeddingFailed => [
                Response::HTTP_BAD_GATEWAY,
                'The embedding provider is currently unavailable.',
            ],

            $throwable instanceof HttpExceptionInterface => [
                $throwable->getStatusCode(),
                Response::HTTP_NOT_FOUND === $throwable->getStatusCode()
                    ? 'Resource not found.'
                    : 'Request could not be processed.',
            ],

            default => [Response::HTTP_INTERNAL_SERVER_ERROR, 'Internal server error.'],
        };
    }
}
