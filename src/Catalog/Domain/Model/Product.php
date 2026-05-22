<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Event\DomainEvent;
use App\Catalog\Domain\Event\ProductWasIndexed;
use App\Catalog\Domain\Event\ProductWasRemoved;
use DateTimeImmutable;

/**
 * Aggregate root of the catalog. A product owns its catalog data and, once
 * indexed, the embedding vector that makes it discoverable.
 *
 * The class is framework-agnostic: persistence is handled by an out-of-domain
 * Doctrine XML mapping, so no ORM annotation ever reaches this file.
 */
final class Product
{
    private ProductId $id;

    private ProductName $name;

    private ProductDescription $description;

    private Money $price;

    private ?EmbeddingVector $embedding = null;

    private ?DateTimeImmutable $indexedAt = null;

    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    private function __construct(
        ProductId $id,
        ProductName $name,
        ProductDescription $description,
        Money $price,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
    }

    public static function register(
        ProductId $id,
        ProductName $name,
        ProductDescription $description,
        Money $price,
    ): self {
        return new self($id, $name, $description, $price);
    }

    /**
     * Updates catalog data. Because the searchable text changed, any previous
     * embedding is now stale and is cleared until the product is re-indexed.
     */
    public function reviseCatalogData(
        ProductName $name,
        ProductDescription $description,
        Money $price,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->embedding = null;
        $this->indexedAt = null;
    }

    /**
     * Attaches a freshly computed embedding and records the fact.
     */
    public function index(EmbeddingVector $embedding, DateTimeImmutable $occurredOn): void
    {
        $this->embedding = $embedding;
        $this->indexedAt = $occurredOn;
        $this->domainEvents[] = new ProductWasIndexed($this->id, $embedding->dimensions(), $occurredOn);
    }

    /**
     * Records that this product is being deleted from the catalog. The event
     * lets the search read model evict the product after the aggregate is gone.
     */
    public function remove(DateTimeImmutable $occurredOn): void
    {
        $this->domainEvents[] = new ProductWasRemoved($this->id, $occurredOn);
    }

    public function isIndexed(): bool
    {
        return null !== $this->embedding;
    }

    /**
     * The text the embedding model should turn into a vector. Keeping this in
     * the aggregate guarantees indexing and (future) re-indexing always feed
     * the model exactly the same input.
     */
    public function searchableText(): string
    {
        return $this->name->value().'. '.$this->description->value();
    }

    public function id(): ProductId
    {
        return $this->id;
    }

    public function name(): ProductName
    {
        return $this->name;
    }

    public function description(): ProductDescription
    {
        return $this->description;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function embedding(): ?EmbeddingVector
    {
        return $this->embedding;
    }

    public function indexedAt(): ?DateTimeImmutable
    {
        return $this->indexedAt;
    }

    /**
     * Hands over and clears the recorded events. The application layer is
     * responsible for publishing them after the aggregate is persisted.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
