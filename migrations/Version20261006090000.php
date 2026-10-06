<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réglages de réponse aux avis par établissement (vouvoiement, ton, signature, consignes)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE establishment ADD COLUMN IF NOT EXISTS reply_formality VARCHAR(4) DEFAULT 'vous' NOT NULL");
        $this->addSql("ALTER TABLE establishment ADD COLUMN IF NOT EXISTS reply_tone VARCHAR(20) DEFAULT 'cordial' NOT NULL");
        $this->addSql('ALTER TABLE establishment ADD COLUMN IF NOT EXISTS reply_signature VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE establishment ADD COLUMN IF NOT EXISTS reply_instructions TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE establishment DROP COLUMN IF EXISTS reply_formality');
        $this->addSql('ALTER TABLE establishment DROP COLUMN IF EXISTS reply_tone');
        $this->addSql('ALTER TABLE establishment DROP COLUMN IF EXISTS reply_signature');
        $this->addSql('ALTER TABLE establishment DROP COLUMN IF EXISTS reply_instructions');
    }
}
