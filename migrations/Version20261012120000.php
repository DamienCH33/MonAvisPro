<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Avis ignorés, suppressions en cascade (suppression de compte) et note comprise entre 1 et 5';
    }

    public function up(Schema $schema): void
    {
        // « Ignorer » dans À traiter
        $this->addSql('ALTER TABLE review ADD COLUMN IF NOT EXISTS ignored_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // Supprimer un compte efface tout en une requête, côté base.
        $this->addSql('ALTER TABLE establishment DROP CONSTRAINT IF EXISTS FK_DBEFB1EE7E3C61F9');
        $this->addSql('ALTER TABLE establishment ADD CONSTRAINT FK_DBEFB1EE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE reset_password_request DROP CONSTRAINT IF EXISTS FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT IF EXISTS FK_794381C68565851');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C68565851 FOREIGN KEY (establishment_id) REFERENCES establishment (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review_analysis DROP CONSTRAINT IF EXISTS FK_23714E3B8565851');
        $this->addSql('ALTER TABLE review_analysis ADD CONSTRAINT FK_23714E3B8565851 FOREIGN KEY (establishment_id) REFERENCES establishment (id) ON DELETE CASCADE NOT DEFERRABLE');

        // Une note hors 1..5 faisait planter le tableau de bord.
        $this->addSql('ALTER TABLE review DROP CONSTRAINT IF EXISTS review_rating_range');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT review_rating_range CHECK (rating BETWEEN 1 AND 5)');

        // Index de la boîte « À traiter » : avis sans réponse, non ignorés, par établissement.
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_review_inbox ON review (establishment_id, rating, published_at) WHERE owner_reply IS NULL AND ignored_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_review_inbox');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT IF EXISTS review_rating_range');

        $this->addSql('ALTER TABLE review_analysis DROP CONSTRAINT IF EXISTS FK_23714E3B8565851');
        $this->addSql('ALTER TABLE review_analysis ADD CONSTRAINT FK_23714E3B8565851 FOREIGN KEY (establishment_id) REFERENCES establishment (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT IF EXISTS FK_794381C68565851');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C68565851 FOREIGN KEY (establishment_id) REFERENCES establishment (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE reset_password_request DROP CONSTRAINT IF EXISTS FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE establishment DROP CONSTRAINT IF EXISTS FK_DBEFB1EE7E3C61F9');
        $this->addSql('ALTER TABLE establishment ADD CONSTRAINT FK_DBEFB1EE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE');

        $this->addSql('ALTER TABLE review DROP COLUMN IF EXISTS ignored_at');
    }
}
