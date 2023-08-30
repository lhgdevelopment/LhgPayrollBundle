<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\LhgPayrollBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220208134541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE lhg_payroll_approval (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            submitted_by_id INT NOT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            status INT NOT NULL,
            total_amount DOUBLE NULL,
            total_duration DOUBLE NULL,
            commission DOUBLE NULL,
            adjustment DOUBLE NULL,
            deduction DOUBLE NULL,
            net_payable DOUBLE NULL, 
            payment_method VARCHAR(255) NULL,
            expected_duration INT NOT NULL,
            creation_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\',
            INDEX IDX_775C89B0A76ED395 (user_id),
            INDEX IDX_775C89B0A76ED396 (submitted_by_id),
            INDEX IDX_A8341CE36BF700BB (status),
            PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE lhg_payroll_approval_history (id INT AUTO_INCREMENT NOT NULL,
        approval_id INT NOT NULL,
        user_id INT NOT NULL,
        status INT NOT NULL,
        date DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\',
        INDEX IDX_A8341CE3FE65F000 (approval_id),
        INDEX IDX_A8341CE3A76ED395 (user_id),
        INDEX IDX_A8341CE36BF700BD (status),
        PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE lhg_payroll_approval ADD CONSTRAINT FK_775C89B0A76ED195 FOREIGN KEY (user_id) REFERENCES kimai2_users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lhg_payroll_approval ADD CONSTRAINT FK_775C89B0A76ED196 FOREIGN KEY (submitted_by_id) REFERENCES kimai2_users (id) ON DELETE CASCADE');
        
        $this->addSql('ALTER TABLE lhg_payroll_approval_history ADD CONSTRAINT FK_A8341CE3FE65F088 FOREIGN KEY (approval_id) REFERENCES lhg_payroll_approval (id)');
        $this->addSql('ALTER TABLE lhg_payroll_approval_history ADD CONSTRAINT FK_A8341CE3A76ED389 FOREIGN KEY (user_id) REFERENCES kimai2_users (id) ON DELETE CASCADE'); 
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lhg_payroll_approval_history DROP FOREIGN KEY FK_775C89B0A76ED195'); 
        $this->addSql('ALTER TABLE lhg_payroll_approval_history DROP FOREIGN KEY FK_A8341CE3FE65F088'); 
        $this->addSql('ALTER TABLE lhg_payroll_approval_history DROP FOREIGN KEY FK_A8341CE3A76ED389'); 
        $this->addSql('DROP TABLE lhg_payroll_approval');
        $this->addSql('DROP TABLE lhg_payroll_approval_history');
        $this->addSql('DROP TABLE lhg_payroll_approval_status');
    }
}
