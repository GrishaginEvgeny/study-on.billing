<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230428022746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transaction RENAME COLUMN value TO amount');
        $this->addSql('ALTER TABLE transaction RENAME COLUMN date TO created_at');
        $this->addSql('ALTER TABLE transaction RENAME COLUMN valid_to TO expired_at');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE transaction RENAME COLUMN amount TO value');
        $this->addSql('ALTER TABLE transaction RENAME COLUMN created_at TO date');
        $this->addSql('ALTER TABLE transaction RENAME COLUMN expired_at TO valid_to');
    }
}
