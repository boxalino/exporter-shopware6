<?php
namespace Boxalino\Exporter\Service;

/**
 * Class ExporterDelta
 *
 * @package Boxalino\Exporter\Service
 */
class ExporterDelta extends ExporterManager
{

    const EXPORTER_ID = 'boxalino.exporter.delta';

    /**
     * Default server timeout
     */
    const SERVER_TIMEOUT_DEFAULT = 60;

    /**
     * @return string
     */
    public function getType(): string
    {
        return ExporterScheduler::BOXALINO_EXPORTER_TYPE_DELTA;
    }

    /**
     * @return string
     */
    public function getExporterId(): string
    {
        return self::EXPORTER_ID;
    }

    /**
     * Get timeout for exporter
     * @return bool|int
     */
    public function getTimeout(string $account) : int
    {
        return self::SERVER_TIMEOUT_DEFAULT;
    }

    /**
     * 2 subsequent deltas can only be run with the time difference allowed
     * the delta after a full export can only be run once the configured time has passed
     *
     * @param string $account
     * @return bool
     * @throws \Exception
     */
    public function exportDeniedOnAccount(string $account) : bool
    {
        $latestDeltaRunDate = $this->getLastSuccessfulExport($account);
        $latestFullRunDate = $this->scheduler->getLastSuccessfulExportByTypeAccount(ExporterScheduler::BOXALINO_EXPORTER_TYPE_FULL, $account);
        $deltaFrequency = $this->config->getDeltaFrequencyMinInterval($account);
        $deltaFullRange = $this->config->getDeltaScheduleTime($account);

        if($latestFullRunDate != min($latestFullRunDate, date('Y-m-d H:i:s', strtotime("-$deltaFullRange min"))))
        {
            return true;
        }

        if($latestDeltaRunDate == min($latestDeltaRunDate, date("Y-m-d H:i:s", strtotime("-$deltaFrequency min"))))
        {
            return false;
        }

        return true;
    }

    /**
     * Delta IDs - the product IDs that have been affected in between data synchronization cycles
     *
     * @return array
     */
    public function getIds() : array
    {
        return $this->ids;
    }

    /**
     * @return bool
     */
    public function getExportFull() : bool
    {
        return false;
    }

}
