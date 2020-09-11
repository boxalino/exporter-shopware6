<?php
namespace Boxalino\Exporter\Service;

use Boxalino\Exporter\Service\ExporterScheduler;

/**
 * Class ExporterFull
 *
 * @package Boxalino\Exporter\Service
 */
class ExporterFull extends ExporterManager
    implements ExporterFullInterface
{

    /**
     * Default server timeout
     */
    const SERVER_TIMEOUT_DEFAULT = 300;

    /**
     * @return string
     */
    public function getType(): string
    {
        return ExporterScheduler::BOXALINO_EXPORTER_TYPE_FULL;
    }

    /**
     * @param string $account
     * @return bool
     */
    public function exportDeniedOnAccount(string $account) : bool
    {
        return false;
    }

    /**
     * Get timeout for exporter
     * @param string $account
     * @return bool|int
     */
    public function getTimeout(string $account) : int
    {
        $customTimeout = $this->config->getExporterTimeout($account);
        if($customTimeout)
        {
            return $customTimeout;
        }

        return self::SERVER_TIMEOUT_DEFAULT;
    }

    /**
     * Full export does not care for ids -- everything is exported
     *
     * @return array
     */
    public function getIds(): array
    {
        return [];
    }

    /**
     * @return bool
     */
    public function isDelta() : bool
    {
        return false;
    }

}
