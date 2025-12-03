<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251203155726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, booking_date DATETIME NOT NULL, status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, client_name VARCHAR(255) DEFAULT NULL, client_email VARCHAR(255) DEFAULT NULL, client_phone VARCHAR(50) DEFAULT NULL, google_calendar_event_id VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, stylist_id INT NOT NULL, service_id INT NOT NULL, INDEX IDX_E00CEDDEA76ED395 (user_id), INDEX IDX_E00CEDDE4066877A (stylist_id), INDEX IDX_E00CEDDEED5CA9E6 (service_id), INDEX idx_booking_date_stylist (booking_date, stylist_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE gallery_image (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, category VARCHAR(50) DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, created_at DATE DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE service_item (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, price VARCHAR(50) NOT NULL, image_filename VARCHAR(255) DEFAULT NULL, icon_class VARCHAR(50) DEFAULT NULL, duration VARCHAR(255) DEFAULT NULL, duration_minutes INT NOT NULL, is_active TINYINT(1) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE stylist (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, bio VARCHAR(255) DEFAULT NULL, photo_url VARCHAR(255) DEFAULT NULL, specialization VARCHAR(100) DEFAULT NULL, is_active TINYINT(1) NOT NULL, user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_4111FFA5A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE stylist_services (stylist_id INT NOT NULL, service_item_id INT NOT NULL, INDEX IDX_D0272BFF4066877A (stylist_id), INDEX IDX_D0272BFFDDEB00C2 (service_item_id), PRIMARY KEY (stylist_id, service_item_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, username VARCHAR(180) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, reset_token VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE4066877A FOREIGN KEY (stylist_id) REFERENCES stylist (id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEED5CA9E6 FOREIGN KEY (service_id) REFERENCES service_item (id)');
        $this->addSql('ALTER TABLE stylist ADD CONSTRAINT FK_4111FFA5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE stylist_services ADD CONSTRAINT FK_D0272BFF4066877A FOREIGN KEY (stylist_id) REFERENCES stylist (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stylist_services ADD CONSTRAINT FK_D0272BFFDDEB00C2 FOREIGN KEY (service_item_id) REFERENCES service_item (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEA76ED395');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE4066877A');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEED5CA9E6');
        $this->addSql('ALTER TABLE stylist DROP FOREIGN KEY FK_4111FFA5A76ED395');
        $this->addSql('ALTER TABLE stylist_services DROP FOREIGN KEY FK_D0272BFF4066877A');
        $this->addSql('ALTER TABLE stylist_services DROP FOREIGN KEY FK_D0272BFFDDEB00C2');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE gallery_image');
        $this->addSql('DROP TABLE service_item');
        $this->addSql('DROP TABLE stylist');
        $this->addSql('DROP TABLE stylist_services');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
