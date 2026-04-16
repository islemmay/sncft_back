<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260412145621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE gare (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, ville VARCHAR(80) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `log` (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(120) NOT NULL, date_heure DATETIME NOT NULL, details LONGTEXT DEFAULT NULL, utilisateur_id INT DEFAULT NULL, INDEX IDX_8F3F68C5FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE passage (id INT AUTO_INCREMENT NOT NULL, heure_theorique TIME DEFAULT NULL, heure_reelle TIME DEFAULT NULL, retard_minutes INT DEFAULT NULL, classification VARCHAR(20) NOT NULL, trajet_id INT NOT NULL, gare_id INT NOT NULL, INDEX IDX_2B258F67D12A823 (trajet_id), INDEX IDX_2B258F6763FD956 (gare_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE statistique (id INT AUTO_INCREMENT NOT NULL, periode_debut DATE NOT NULL, periode_fin DATE NOT NULL, nb_retards INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE train (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(32) NOT NULL, type VARCHAR(64) NOT NULL, capacite INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE trajet (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, ville_depart VARCHAR(80) NOT NULL, ville_arrivee VARCHAR(80) NOT NULL, train_id INT NOT NULL, INDEX IDX_2B5BA98C23BCD4D0 (train_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, service VARCHAR(10) NOT NULL, matricule VARCHAR(50) NOT NULL, derniere_connexion DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_UTILISATEUR_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `log` ADD CONSTRAINT FK_8F3F68C5FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE passage ADD CONSTRAINT FK_2B258F67D12A823 FOREIGN KEY (trajet_id) REFERENCES trajet (id)');
        $this->addSql('ALTER TABLE passage ADD CONSTRAINT FK_2B258F6763FD956 FOREIGN KEY (gare_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE trajet ADD CONSTRAINT FK_2B5BA98C23BCD4D0 FOREIGN KEY (train_id) REFERENCES train (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `log` DROP FOREIGN KEY FK_8F3F68C5FB88E14F');
        $this->addSql('ALTER TABLE passage DROP FOREIGN KEY FK_2B258F67D12A823');
        $this->addSql('ALTER TABLE passage DROP FOREIGN KEY FK_2B258F6763FD956');
        $this->addSql('ALTER TABLE trajet DROP FOREIGN KEY FK_2B5BA98C23BCD4D0');
        $this->addSql('DROP TABLE gare');
        $this->addSql('DROP TABLE `log`');
        $this->addSql('DROP TABLE passage');
        $this->addSql('DROP TABLE statistique');
        $this->addSql('DROP TABLE train');
        $this->addSql('DROP TABLE trajet');
        $this->addSql('DROP TABLE utilisateur');
    }
}
