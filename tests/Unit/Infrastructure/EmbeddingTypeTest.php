<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Catalog\Domain\Model\EmbeddingVector;
use App\Catalog\Infrastructure\Persistence\Doctrine\Type\EmbeddingType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

final class EmbeddingTypeTest extends TestCase
{
    private EmbeddingType $type;
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new EmbeddingType();
        $this->platform = $this->createMock(AbstractPlatform::class);
    }

    public function testItConvertsAVectorToJsonAndBack(): void
    {
        $vector = new EmbeddingVector([0.25, -0.5, 0.75]);

        $database = $this->type->convertToDatabaseValue($vector, $this->platform);
        self::assertSame('[0.25,-0.5,0.75]', $database);

        $restored = $this->type->convertToPHPValue($database, $this->platform);
        self::assertInstanceOf(EmbeddingVector::class, $restored);
        self::assertSame([0.25, -0.5, 0.75], $restored->components());
    }

    public function testItPassesNullThrough(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testItReturnsAVectorUnchanged(): void
    {
        $vector = new EmbeddingVector([1.0]);

        self::assertSame($vector, $this->type->convertToPHPValue($vector, $this->platform));
    }
}
