<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Добавление индексов для улучшения производительности
 */
final class Version20250819152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавление индексов для улучшения производительности';
    }

    public function up(Schema $schema): void
    {
        // Индекс для поиска пользователей по email
        $this->addSql('CREATE INDEX idx_user_email ON "user" (email)');
        
        // Индекс для поиска активных токенов
        $this->addSql('CREATE INDEX idx_user_api_token_active ON user_api_token (user_id, is_active)');
        
        // Индекс для поиска истекших токенов
        $this->addSql('CREATE INDEX idx_user_api_token_expires ON user_api_token (expires_at) WHERE is_active = true');
        
        // Индекс для поиска курсов по коду
        $this->addSql('CREATE INDEX idx_course_code ON course (code)');
        
        // Индекс для поиска уроков по курсу и сортировке
        $this->addSql('CREATE INDEX idx_lesson_course_sort ON lesson (course_id, sort)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_user_email');
        $this->addSql('DROP INDEX idx_user_api_token_active');
        $this->addSql('DROP INDEX idx_user_api_token_expires');
        $this->addSql('DROP INDEX idx_course_code');
        $this->addSql('DROP INDEX idx_lesson_course_sort');
    }
}
