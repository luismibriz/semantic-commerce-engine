<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the products table — the relational write model and single source
 * of truth. The embedding is stored as JSONB so the Elasticsearch read model
 * can always be rebuilt from here; similarity search itself runs in ES.
 */
final class Version20260521120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the products table (Product write model).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE products (
                id UUID NOT NULL,
                name VARCHAR(150) NOT NULL,
                description TEXT NOT NULL,
                price_amount INT NOT NULL,
                price_currency VARCHAR(3) NOT NULL,
                embedding JSONB DEFAULT NULL,
                indexed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE products');
    }
}
