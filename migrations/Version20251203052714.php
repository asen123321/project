<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PostgreSQL Migration - Auto-generated and converted from MySQL
 */
final class Version20251203052714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create all tables for PostgreSQL database';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE booking_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE gallery_image_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE service_item_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE stylist_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "user_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE messenger_messages_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql('CREATE TABLE booking (id INT NOT NULL, user_id INT NOT NULL, stylist_id INT NOT NULL, service_id INT NOT NULL, booking_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(20) NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, client_name VARCHAR(255) DEFAULT NULL, client_email VARCHAR(255) DEFAULT NULL, client_phone VARCHAR(50) DEFAULT NULL, google_calendar_event_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E00CEDDEA76ED395 ON booking (user_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE4066877A ON booking (stylist_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDEED5CA9E6 ON booking (service_id)');
        $this->addSql('CREATE INDEX idx_booking_date_stylist ON booking (booking_date, stylist_id)');

        $this->addSql('CREATE TABLE gallery_image (id INT NOT NULL, title VARCHAR(255) DEFAULT NULL, category VARCHAR(50) DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, created_at DATE DEFAULT NULL, PRIMARY KEY(id))');

        $this->addSql('CREATE TABLE service_item (id INT NOT NULL, title VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, price VARCHAR(50) NOT NULL, image_filename VARCHAR(255) DEFAULT NULL, icon_class VARCHAR(50) DEFAULT NULL, duration VARCHAR(255) DEFAULT NULL, duration_minutes INT NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))');

        $this->addSql('CREATE TABLE stylist (id INT NOT NULL, user_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, bio VARCHAR(255) DEFAULT NULL, photo_url VARCHAR(255) DEFAULT NULL, specialization VARCHAR(100) DEFAULT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4111FFA5A76ED395 ON stylist (user_id)');

        $this->addSql('CREATE TABLE stylist_services (stylist_id INT NOT NULL, service_item_id INT NOT NULL, PRIMARY KEY(stylist_id, service_item_id))');
        $this->addSql('CREATE INDEX IDX_D0272BFF4066877A ON stylist_services (stylist_id)');
        $this->addSql('CREATE INDEX IDX_D0272BFFDDEB00C2 ON stylist_services (service_item_id)');

        $this->addSql('CREATE TABLE "user" (id INT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, username VARCHAR(180) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, reset_token VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON "user" (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)');

        $this->addSql('CREATE TABLE messenger_messages (id BIGINT NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE4066877A FOREIGN KEY (stylist_id) REFERENCES stylist (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEED5CA9E6 FOREIGN KEY (service_id) REFERENCES service_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stylist ADD CONSTRAINT FK_4111FFA5A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stylist_services ADD CONSTRAINT FK_D0272BFF4066877A FOREIGN KEY (stylist_id) REFERENCES stylist (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stylist_services ADD CONSTRAINT FK_D0272BFFDDEB00C2 FOREIGN KEY (service_item_id) REFERENCES service_item (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE booking_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE gallery_image_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE service_item_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE stylist_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE "user_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE messenger_messages_id_seq CASCADE');

        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDEA76ED395');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE4066877A');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDEED5CA9E6');
        $this->addSql('ALTER TABLE stylist DROP CONSTRAINT FK_4111FFA5A76ED395');
        $this->addSql('ALTER TABLE stylist_services DROP CONSTRAINT FK_D0272BFF4066877A');
        $this->addSql('ALTER TABLE stylist_services DROP CONSTRAINT FK_D0272BFFDDEB00C2');

        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE gallery_image');
        $this->addSql('DROP TABLE service_item');
        $this->addSql('DROP TABLE stylist');
        $this->addSql('DROP TABLE stylist_services');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
