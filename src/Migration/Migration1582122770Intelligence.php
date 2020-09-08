<?php declare(strict_types=1);
namespace Boxalino\Exporter\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Class Migration1582122770Intelligence
 *
 * @package Boxalino\Exporter\Migration
 */
class Migration1582122770Intelligence extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1582122770;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE IF NOT EXISTS `boxalino_export` (
              `account` VARCHAR(128) NOT NULL,
              `type` VARCHAR(128) NOT NULL,
              `export_date` DATETIME,
              `status` VARCHAR(128) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`account`, `type`)
            );
        ');

        $connection->executeQuery('
            CREATE TABLE IF NOT EXISTS `boxalino_cron_export` (
              `export_date` DATETIME
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

}
