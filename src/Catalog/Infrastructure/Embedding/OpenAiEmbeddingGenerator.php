<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Embedding;

use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Domain\Model\EmbeddingVector;
use App\Catalog\Domain\Service\EmbeddingGenerator;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls the OpenAI embeddings endpoint.
 *
 * The dimension is requested explicitly so it always matches the Elasticsearch
 * dense_vector mapping; OpenAI's text-embedding-3-* models support shortening.
 */
final class OpenAiEmbeddingGenerator implements EmbeddingGenerator
{
    private const ENDPOINT = 'https://api.openai.com/v1/embeddings';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $dimensions,
    ) {
    }

    public function embed(string $text): EmbeddingVector
    {
        $text = trim($text);
        if ('' === $text) {
            throw InvalidArgument::because('Cannot embed empty text.');
        }

        if ('' === $this->apiKey) {
            throw EmbeddingFailed::provider('openai', 'no API key configured (OPENAI_API_KEY).');
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'auth_bearer' => $this->apiKey,
                'json' => [
                    'model' => $this->model,
                    'input' => $text,
                    'dimensions' => $this->dimensions,
                ],
                'timeout' => 15,
            ]);

            $payload = $response->toArray();
        } catch (HttpExceptionInterface $e) {
            throw EmbeddingFailed::provider('openai', $e->getMessage(), $e);
        }

        $components = $payload['data'][0]['embedding'] ?? null;
        if (!\is_array($components) || [] === $components) {
            throw EmbeddingFailed::provider('openai', 'response did not contain an embedding.');
        }

        return new EmbeddingVector($components);
    }
}
