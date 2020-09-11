<?php
namespace Boxalino\Exporter\Service;

/**
 * Interface ExporterConfigurationInterface
 *
 * @package Boxalino\Exporter\Service
 */
interface ExporterConfigurationInterface
{

    /**
     * @param string $account
     * @return mixed
     */
    public function setAccount(string $account);

    /**
     * @return array
     */
    public function getAccounts() : array;

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getPassword() : string;

    /**
     * @return mixed
     * @throws \Exception
     */
    public function useDevIndex() : bool;

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getChannelId() : string;

    /**
     * @return []
     * @throws \Exception
     */
    public function getLanguages() : array;

    /**
     * @throws \Exception
     */
    public function getChannelDefaultLanguageId() : string;

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getCustomerGroupId()  : string;

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getChannelRootCategoryId() : string;

    /**
     * @return bool
     * @throws \Exception
     */
    public function isCustomersExportEnabled() : bool;

    /**
     * @return bool
     * @throws \Exception
     */
    public function isTransactionsExportEnabled() : bool;

    /**
     * @return bool
     * @throws \Exception
     */
    public function isVoucherExportEnabled() : bool;

    /**
     * @return string
     * @throws \Exception
     */
    public function isTransactionExportIncremental() : string;

    /**
     * Getting additional tables for each entity to be exported (products, customers, transactions)
     *
     * @param string $type
     * @return array
     * @throws \Exception
     */
    public function getExtraTablesByComponent(string $type) : array;

    /**
     * @return null | string
     */
    public function getExportTemporaryArchivePath() : ?string;

    /**
     * @return bool
     * @throws \Exception
     */
    public function exportProductImages() : bool;

    /**
     * @return bool
     * @throws \Exception
     */
    public function exportProductUrl() : bool;

    /**
     * @return bool
     * @throws \Exception
     */
    public function publishConfigurationChanges() : bool;

    /**
     * Full data sync timeout (default 300)
     *
     * @return int
     * @throws \Exception
     */
    public function getExporterTimeout() : int;

    /**
     * Time interval after a full data export that a delta is allowed to run
     * It is set in order to avoid overlapping index updates
     *
     * A full data synchronization can take up to 2h (depending on the size of the export and the complexity of data)
     * For this reason, the daily full data synchronization must happen when there is the least traffic on the store
     *
     * @return int
     * @throws \Exception
     */
    public function getDeltaScheduleTime() : int;

    /**
     * Minimum time interval between 2 deltas to allow a run (minutes)
     *
     * @return int
     * @throws \Exception
     */
    public function getDeltaFrequencyMinInterval() : int;
    
}
