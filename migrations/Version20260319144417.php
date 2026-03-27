<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319144417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE establishment (id UUID NOT NULL, name VARCHAR(255) NOT NULL, place_id VARCHAR(255) NOT NULL, address VARCHAR(500) NOT NULL, alerts_enabled BOOLEAN NOT NULL, last_sync_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DBEFB1EEDA6A219 ON establishment (place_id)');
        $this->addSql('CREATE INDEX IDX_DBEFB1EE7E3C61F9 ON establishment (owner_id)');
        $this->addSql('CREATE TABLE review (id UUID NOT NULL, google_author VARCHAR(255) NOT NULL, google_author_photo VARCHAR(500) DEFAULT NULL, rating INT NOT NULL, text TEXT DEFAULT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, google_review_id VARCHAR(255) NOT NULL, is_read BOOLEAN NOT NULL, establishment_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_794381C6B9F1B03 ON review (google_review_id)');
        $this->addSql('CREATE INDEX IDX_794381C68565851 ON review (establishment_id)');
        $this->addSql('CREATE TABLE review_analysis (id UUID NOT NULL, positive_themes JSON NOT NULL, negative_themes JSON NOT NULL, action_suggestion TEXT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, establishment_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_23714E3B8565851 ON review_analysis (establishment_id)');
        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, alerts_enabled BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('ALTER TABLE establishment ADD CONSTRAINT FK_DBEFB1EE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C68565851 FOREIGN KEY (establishment_id) REFERENCES establishment (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review_analysis ADD CONSTRAINT FK_23714E3B8565851 FOREIGN KEY (establishment_id) REFERENCES establishment (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE establishment DROP CONSTRAINT FK_DBEFB1EE7E3C61F9');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C68565851');
        $this->addSql('ALTER TABLE review_analysis DROP CONSTRAINT FK_23714E3B8565851');
        $this->addSql('DROP TABLE establishment');
        $this->addSql('DROP TABLE review');
        $this->addSql('DROP TABLE review_analysis');
        $this->addSql('DROP TABLE "user"');
    }
}
