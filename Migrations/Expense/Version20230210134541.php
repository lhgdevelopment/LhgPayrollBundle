<?php

declare(strict_types=1);

namespace KimaiPlugin\LhgPayrollBundle\Migrations\Expense;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230210134541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Make the user_id column nullable
        $this->addSql('ALTER TABLE kimai2_expense MODIFY user_id INT DEFAULT NULL');

        // Create the Vendor table
        $this->addSql('ALTER TABLE kimai2_expense ADD vendor_id INT DEFAULT NULL');

        // Add the foreign key constraint to reference the Vendor table
        $this->addSql('ALTER TABLE kimai2_expense ADD CONSTRAINT FK_kimai2_expense_vendor FOREIGN KEY (vendor_id) REFERENCES lhg_payroll_vendor (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop the foreign key constraint
        $this->addSql('ALTER TABLE kimai2_expense DROP FOREIGN KEY FK_kimai2_expense_vendor');

        // Remove the vendor_id column (if needed)
        $this->addSql('ALTER TABLE kimai2_expense DROP COLUMN vendor_id');

        // Make the user_id column non-nullable (modify as needed)
        $this->addSql('ALTER TABLE kimai2_expense MODIFY user_id INT NOT NULL');
    }
}
