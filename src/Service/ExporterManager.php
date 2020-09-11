<?php
namespace Boxalino\Exporter\Service;

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
     * @var ExporterConfigurationInterface
     */
    protected $config;

    /**
     * @var ExporterScheduler
     */
    protected $scheduler;

    /**
     * @var ExporterServiceInterface
     */
    protected $exporterService;

    /**
     * @var array
     */
    protected $ids = [];

    /**
     * @var null | string
     */
    protected $account = null;

    /**
     * ExporterManager constructor.
     * @param LoggerInterface $boxalinoLogger
     * @param ExporterConfigurationInterface $exporterConfigurator
     * @param \Boxalino\Exporter\Service\ExporterScheduler $scheduler
     * @param \Boxalino\Exporter\Service\ExporterServiceInterface $exporterService
     * @param string $exportPath
     */
    public function __construct(
        LoggerInterface $boxalinoLogger,
        ExporterConfigurationInterface $exporterConfigurator,
        ExporterScheduler $scheduler,
        ExporterServiceInterface $exporterService
    ) {
        $this->config = $exporterConfigurator;
        $this->logger = $boxalinoLogger;
        $this->scheduler = $scheduler;
        $this->exporterService = $exporterService;
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
            $this->config->setAccount($account);
            try{
                if($this->exportAllowedByAccount($account))
                {
                    $exporterHasRun = true;
                    $this->exporterService
                        ->setAccount($account)
                        ->setType($this->getType())
                        ->setIsDelta($this->isDelta())
                        ->setTimeout($this->getTimeout($account))
                        ->export();
                }
            } catch (\Exception $exception) {
                $errorMessages[] = $exception->getMessage();
                continue;
            }
        }

        if(!$exporterHasRun)
        {
            $this->logger->warning("BoxalinoExporterManager: The {$this->getType()} exporter did not run. Execution feedback: " . implode("\n", $errorMessages));
            return false;
        }

        if(empty($errorMessages) && $exporterHasRun)
        {
            return true;
        }

        throw new \Exception("BoxalinoExporterManager: export failed with messages: " . implode(",", $errorMessages));
    }

    /**
     * @param string $account
     * @return bool
     */
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

    /**
     * @param array $ids
     * @return $this
     */
    public function setIds(array $ids) : self
    {
        $this->ids = $ids;
        return $this;
    }

    abstract function getTimeout(string $account) : int;
    abstract function getIds() : array;
    abstract function exportDeniedOnAccount(string $account) : bool;
    abstract function getType() : string;
    abstract function isDelta() : bool;

}
