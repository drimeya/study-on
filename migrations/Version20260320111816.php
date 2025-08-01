<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320111816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_course_code');
        $this->addSql('DROP INDEX idx_lesson_course_sort');
        $this->addSql('DROP INDEX idx_user_email');
        $this->addSql('ALTER TABLE "user" ADD refresh_token VARCHAR(1000) DEFAULT NULL');
        $this->addSql('DROP INDEX idx_user_api_token_active');
        $this->addSql('DROP INDEX idx_user_api_token_expires');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE INDEX idx_course_code ON course (code)');
        $this->addSql('CREATE INDEX idx_lesson_course_sort ON lesson (course_id, sort)');
        $this->addSql('ALTER TABLE "user" DROP refresh_token');
        $this->addSql('CREATE INDEX idx_user_email ON "user" (email)');
        $this->addSql('CREATE INDEX idx_user_api_token_active ON user_api_token (user_id, is_active)');
        $this->addSql('CREATE INDEX idx_user_api_token_expires ON user_api_token (expires_at) WHERE (is_active = true)');
    }
}
