<?php

declare(strict_types=1);

namespace KimaiPlugin\LhgPayrollBundle\Migrations\Vendor;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230208134541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Create the Vendor table
        $this->addSql('CREATE TABLE lhg_payroll_vendor (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            website VARCHAR(255) NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Create the Vendor Payment table
        $this->addSql('CREATE TABLE lhg_payroll_vendor_payment (
            id INT AUTO_INCREMENT NOT NULL,
            vendor_id INT NOT NULL,
            project_id INT NOT NULL,
            billing_type VARCHAR(255) NOT NULL,
            amount DOUBLE PRECISION NOT NULL,
            note VARCHAR(255) NOT NULL,
            description LONGTEXT NOT NULL,
            PRIMARY KEY (id),
            INDEX IDX_vendor_payment_vendor_id (vendor_id),
            INDEX IDX_vendor_payment_project_id (project_id),  -- Add index for project_id
            CONSTRAINT FK_vendor_payment_vendor_id FOREIGN KEY (vendor_id) REFERENCES lhg_payroll_vendor (id) ON DELETE CASCADE,
            CONSTRAINT FK_vendor_payment_project_id FOREIGN KEY (project_id) REFERENCES kimai2_projects (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Add any other indexes or constraints as needed
        
        // Create additional tables or modify as required
        
    }

    public function down(Schema $schema): void
    {
        // Drop the Vendor Payment table
        $this->addSql('DROP TABLE lhg_payroll_vendor_payment');
        
        // Drop the Vendor table
        $this->addSql('DROP TABLE lhg_payroll_vendor');
        
        // Drop any additional tables if needed
    }
}
