<?php

declare(strict_types=1);

namespace KimaiPlugin\LhgPayrollBundle\Migrations\Talent;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20240321000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create speciality tables';
    }

    public function up(Schema $schema): void
    {
        $specialityTable = $schema->createTable('lhg_speciality');
        $specialityTable->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $specialityTable->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
        $specialityTable->addColumn('description', 'text', ['notnull' => false]);
        $specialityTable->setPrimaryKey(['id']);
        $specialityTable->addUniqueIndex(['name'], 'UNIQ_SPECIALITY_NAME');

        $userSpecialityTable = $schema->createTable('lhg_user_speciality');
        $userSpecialityTable->addColumn('user_id', 'integer', ['notnull' => true]);
        $userSpecialityTable->addColumn('speciality_id', 'integer', ['notnull' => true]);
        $userSpecialityTable->setPrimaryKey(['user_id', 'speciality_id']);
        $userSpecialityTable->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
        $userSpecialityTable->addForeignKeyConstraint('lhg_speciality', ['speciality_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('lhg_user_speciality');
        $schema->dropTable('lhg_speciality');
    }
} 