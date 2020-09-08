<?php
namespace Boxalino\Exporter\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Class ExporterScheduler
 * The ExporterScheduler is a resource helper (DB) that checks the status of the running/existing processes
 * (to not be confused with the Shopware6 Scheduler)
 *
 * @package Boxalino\Exporter\Service
 */
class ExporterScheduler
{

    CONST BOXALINO_EXPORTER_TYPE_DELTA = "delta";
    CONST BOXALINO_EXPORTER_TYPE_FULL = "full";

    CONST BOXALINO_EXPORTER_STATUS_SUCCESS = "success";
    CONST BOXALINO_EXPORTER_STATUS_FAIL = "fail";
    CONST BOXALINO_EXPORTER_STATUS_PROCESSING = "processing";

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Connection $connection
     */
    public function __construct(
        Connection $connection,
        LoggerInterface $boxalinoLogger
    ) {
        $this->connection = $connection;
        $this->logger = $boxalinoLogger;
    }

    /**
     * @param string $account
     * @param string $type
     * @return string
     */
    public function getLastExportByAccountType(string $account, string $type) : ?string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['export_date'])
            ->from("boxalino_export")
            ->andWhere("account = :account")
            ->andWhere("type = :type")
            ->orderBy("export_date", "DESC")
            ->setMaxResults(1)
            ->setParameter("account", $account)
            ->setParameter("type", $type);
        $latestRecord = $query->execute()->fetchColumn();
        if(empty($latestRecord['export_date']) || is_null($latestRecord['export_date']))
        {
            return null;
        }

        return $latestRecord['export_date'];
    }

    /**
     * @param string $type
     * @param string $account
     * @return string
     */
    public function getLastSuccessfulExportByTypeAccount(string $type, string $account) : ?string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['export_date'])
            ->from("boxalino_export")
            ->andWhere("account = :account")
            ->andWhere("status = :status")
            ->andWhere("type = :type")
            ->orderBy("export_date", "DESC")
            ->setMaxResults(1)
            ->setParameter("account", $account)
            ->setParameter("type", $type)
            ->setParameter("status", self::BOXALINO_EXPORTER_STATUS_SUCCESS);
        $latestRecord = $query->execute()->fetchColumn();
        if(empty($latestRecord['export_date']) || is_null($latestRecord['export_date']))
        {
            return null;
        }

        return $latestRecord['export_date'];
    }

    /**
     * The export table is truncated
     * @param string $type
     * @param string $account
     * @return bool
     */
    public function clearExportTable(string $type, string $account) : bool
    {
        if(is_null($type))
        {
            $this->connection->delete("boxalino_export", ["account" => $account]);
            return true;
        }

        $this->connection->delete("boxalino_export", ["account" => $account, "type" => $type]);
        return true;
    }


    /**
     * The export table is displayed
     * @return []
     */
    public function viewExportTable() : array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select()->from("boxalino_export");

        return $query->execute()->fetchAll();
    }

    /**
     * 1. Check if there is any active running process with status PROCESSING
     * 1.1 If there is none - the full export can start regardless; if it is a delta export - it is allowed to be run at least 30min after a full one
     * 2. When there are processes with "PROCESSING" state:
     * 2.1 if the time difference is less than 15 min - stop store export
     * 2.2 if it is an older process which got stuck - allow the process to start if it does not block a prior full export on the account
     *
     * @param string $type
     * @param string $account
     * @return bool
     */
    public function canStartExport(string $type, string $account) : bool
    {
        $stuckProcessTime = date("Y-m-d H:i:s", strtotime("-15min"));
        $query = $this->connection->createQueryBuilder();
        $query->select(['export_date', 'account'])
            ->from('boxalino_export')
            ->andWhere('account <> :account')
            ->andWhere('status = :status')
            ->setParameter('account', $account)
            ->setParameter('status', self::BOXALINO_EXPORTER_STATUS_PROCESSING);

        $processes = $query->execute()->fetchAll();
        if(empty($processes))
        {
            if($type == self::BOXALINO_EXPORTER_TYPE_FULL)
            {
                return true;
            }
        }

        foreach($processes as $process)
        {
            if($stuckProcessTime == min($stuckProcessTime, $process['export_date']))
            {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $account
     * @return mixed
     */
    public function getLatestFullExportPerAccount(string $account)
    {
        $latestRunOnAccount = $this->connection->createQueryBuilder();
        $latestRunOnAccount->select(['export_date'])
            ->from('boxalino_export')
            ->andWhere('account = :account')
            ->andWhere('type = :type')
            ->setParameter('account', $account)
            ->setParameter('type', self::BOXALINO_EXPORTER_TYPE_FULL);

        return $latestRunOnAccount->execute()->fetchColumn($latestRunOnAccount);
    }

    /**
     * @param string $date
     * @param string $type
     * @param string $status
     * @param string $account
     * @return void
     * @throws \Doctrine\DBAL\DBALException
     */
    public function updateScheduler(string $date, string $type, string $status, string $account) : void
    {
        $dataBind = [
            $account,
            $type,
            $date,
            $status
        ];

        $query="INSERT INTO boxalino_export (account, type, export_date, status) VALUES (?, ?, ?, ?) ".
            "ON DUPLICATE KEY UPDATE export_date = '$date', status = '$status', updated_at=NOW();";

        $this->connection->executeUpdate(
            $query,
            $dataBind
        );
    }

}
