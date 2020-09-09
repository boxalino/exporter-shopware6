<?php
namespace Boxalino\Exporter\Service;

use Boxalino\Exporter\Service\Util\Configuration;
use \Psr\Log\LoggerInterface;

/**
 * Class ExporterManager
 * Handles generic logic for the data exporting to Boxalino DI server
 *
 * @package Boxalino\Exporter\Service
 */
abstract class ExporterManager
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var Configuration containing the access to the configuration of each store to export
     */
    protected $config = null;

    /**
     * @var ExporterScheduler
     */
    protected $scheduler;

    /**
     * @var null
     */
    protected $latestRun = null;

    /**
     * @var null
     */
    protected $account = null;

    /**
     * @var ExporterService
     */
    protected $exporterService;

    /**
     * @var string
     */
    protected $exportPath;

    /**
     * @var array
     */
    protected $ids = [];

    /**
     * ExporterManager constructor.
     * @param LoggerInterface $boxalinoLogger
     * @param Configuration $exporterConfigurator
     * @param \Boxalino\Exporter\Service\ExporterScheduler $scheduler
     * @param \Boxalino\Exporter\Service\ExporterService $exporterService
     * @param string $exportPath
     */
    public function __construct(
        LoggerInterface $boxalinoLogger,
        Configuration $exporterConfigurator,
        ExporterScheduler $scheduler,
        ExporterService $exporterService,
        string $exportPath
    ) {
        $this->config = $exporterConfigurator;
        $this->logger = $boxalinoLogger;
        $this->scheduler = $scheduler;
        $this->exporterService = $exporterService;
        $this->exportPath = $exportPath;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function export() : bool
    {
        $accounts = $this->getAccounts();
        if(empty($accounts))
        {
            $this->logger->info("BoxalinoExporter: no active configurations found on the stores. Process cancelled.");
            return false;
        }

        $errorMessages = [];
        $this->logger->info("BoxalinoExporter: starting Boxalino {$this->getType()} exporter process.");
        $exporterHasRun = false;
        foreach($accounts as $account)
        {
            $this->logger->info($account);
            try{
                if($this->exportAllowedByAccount($account))
                {
                    $exporterHasRun = true;
                    $this->exporterService
                        ->setAccount($account)
                        ->setType($this->getType())
                        ->setExporterId($this->getExporterId())
                        ->setIsFull($this->getExportFull())
                        ->setTimeout($this->getTimeout($account))
                        ->setDirectory($this->exportPath)
                        ->export();
                }
            } catch (\Exception $exception) {
                $errorMessages[] = $exception->getMessage();
                continue;
            }
        }

        if(!$exporterHasRun)
        {
            return false;
        }

        if(empty($errorMessages) && $exporterHasRun)
        {
            return true;
        }

        throw new \Exception("BoxalinoExporter: export failed with messages: " . implode(",", $errorMessages));
    }


    public function exportAllowedByAccount(string $account) : bool
    {
        if($this->scheduler->canStartExport($this->getType(), $account) && !$this->exportDeniedOnAccount($account))
        {
            return true;
        }

        $this->logger->info("BoxalinoExporter: The {$this->getType()} export is denied permission to run on account {$account}. Check your exporter configurations.");
        return false;
    }

    /**
     * Returns either the specific account to run the exporter for OR the list of accounts configured for all the channels
     *
     * @return array
     */
    public function getAccounts() : array
    {
        if(is_null($this->account))
        {
            return $this->config->getAccounts();
        }

        return [$this->account];
    }

    /**
     * Get indexer latest updated at
     *
     * @param string | null $account
     * @return string
     */
    public function getLastSuccessfulExport(string $account) : ?string
    {
        return $this->scheduler->getLastSuccessfulExportByTypeAccount($this->getType(), $account);
    }

    /**
     * @param string $account
     * @return ExporterManager
     */
    public function setAccount(string $account) : self
    {
        $this->account = $account;
        return $this;
    }

    public function setIds(array $ids) : self
    {
        $this->ids = $ids;
        return $this;
    }

    abstract function getTimeout(string $account) : int;
    abstract function getIds() : array;
    abstract function exportDeniedOnAccount(string $account) : bool;
    abstract function getType() : string;
    abstract function getExporterId() : string;
    abstract function getExportFull() : bool;

}
