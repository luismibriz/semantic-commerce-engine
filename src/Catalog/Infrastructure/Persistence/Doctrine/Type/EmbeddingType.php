<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\Model\EmbeddingVector;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

/**
 * Persists an EmbeddingVector as a JSONB column.
 *
 * The relational store keeps the vector only to remain the single source of
 * truth (so the Elasticsearch read model can always be rebuilt). Similarity
 * search itself never runs here — it runs in Elasticsearch.
 */
final class EmbeddingType extends Type
{
    public const NAME = 'embedding';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'JSONB';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EmbeddingVector
    {
        if (null === $value || $value instanceof EmbeddingVector) {
            return $value;
        }

        $components = json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($components)) {
            throw new UnexpectedValueException('Stored embedding is not a JSON array.');
        }

        return new EmbeddingVector($components);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        $components = $value instanceof EmbeddingVector ? $value->components() : $value;

        return json_encode($components, \JSON_THROW_ON_ERROR);
    }
}
