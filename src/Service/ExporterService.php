<?php
namespace Boxalino\Exporter\Service;

use Boxalino\Exporter\Service\Component\Customer;
use Boxalino\Exporter\Service\Component\Order;
use Boxalino\Exporter\Service\Component\Product;
use Boxalino\Exporter\Service\ExporterScheduler;
use Boxalino\Exporter\Service\Util\Configuration;
use Boxalino\Exporter\Service\Util\FileHandler;
use Boxalino\Exporter\Service\Util\ContentLibrary;
use Doctrine\DBAL\Connection;
use LogicException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Psr\Log\LoggerInterface;

/**
 * Class Exporter
 * Data exporting service
 *
 * @package Boxalino\Exporter\Service
 */
class ExporterService
{

    /**
     * @var bool
     */
    protected $isFull = false;

    /**
     * @var null
     */
    protected $lastExport = null;
    protected $customerExporter;
    protected $transactionExporter;
    protected $productExporter;

    protected $directory = null;
    protected $logger;
    protected $scheduler;
    protected $fileHandler;
    protected $configurator;

    protected $library;
    protected $account;
    protected $type;
    protected $exporterId;
    protected $timeout;


    public function __construct(
        Order $transactionExporter,
        Customer $customerExporter,
        Product $productExporter,
        LoggerInterface $boxalinoLogger,
        Configuration $exporterConfigurator,
        ContentLibrary $library,
        FileHandler $fileHandler,
        ExporterScheduler $scheduler
    ) {
        $this->transactionExporter = $transactionExporter;
        $this->customerExporter = $customerExporter;
        $this->productExporter = $productExporter;

        $this->configurator = $exporterConfigurator;
        $this->logger = $boxalinoLogger;
        $this->library = $library;
        $this->fileHandler = $fileHandler;
        $this->scheduler = $scheduler;
    }

    /**
     * @return string
     * @throws \Doctrine\DBAL\DBALException
     */
    public function export()
    {
        set_time_limit(7200);
        $account = $this->getAccount();
        $directory = $this->getDirectory();

        try {
            if(empty($account) || empty($directory))
            {
                throw new \Exception("BoxalinoExporter: Cancelled Boxalino {$this->getType()} data sync. The account/directory path name can not be empty.");
            }

            $this->addSchedulerStatus(ExporterScheduler::BOXALINO_EXPORTER_STATUS_PROCESSING);

            $this->logger->info("BoxalinoExporter: Exporting store ID : {$this->configurator->getAccountChannelId($account)}");
            $this->initFiles();
            $this->initLibrary();
            $this->verifyCredentials();
            $this->exportProducts();
            $this->exportCustomers();
            $this->exportOrders();

            if ($this->productExporter->getSuccessOnComponentExport())
            {
                $this->prepareXmlConfigurations();
                $this->pushToDI();
            } else {
                $this->logger->info('BoxalinoExporter: NO PRODUCTS FOUND. Export finished on account: ' . $account);
            }

            $this->logger->info("BoxalinoExporter: End of Boxalino {$this->getType()} data sync on account {$account}");
            $this->addSchedulerStatus(ExporterScheduler::BOXALINO_EXPORTER_STATUS_SUCCESS);
            $this->logger->info("BoxalinoExporter: Updated boxalino_exports {$this->getType()} data sync end for account {$account}");
        } catch(\Throwable $e) {
            $this->logger->error("BoxalinoExporter: failed with exception: " . $e->getMessage());
            $this->logger->error($e->getTraceAsString());

            $this->logger->info("BoxalinoExporter: Update boxalino_exports {$this->getType()} failed data sync end for account {$account}");
            $this->addSchedulerStatus(ExporterScheduler::BOXALINO_EXPORTER_STATUS_FAIL);

            $systemMessages[] = "BoxalinoExporter: failed with exception: ". $e->getMessage();
            throw new \Exception(implode(",",$systemMessages));
        }

        $this->logger->info("BoxalinoExporter: End of Boxalino {$this->getType()} data sync on account {$account}");
    }

    /**
     * Initializes export directory and files handler for the process
     */
    protected function initFiles() : void
    {
        $this->logger->info("BoxalinoExporter: Initialize files for account: {$this->getAccount()}");

        $this->getFiles()->setAccount($this->getAccount())
            ->setType($this->getType())
            ->setMainDir($this->getDirectory())
            ->init();
    }

    /**
     * Initializes the xml/zip content library
     */
    protected function initLibrary() : void
    {
        $this->logger->info("BoxalinoExporter: Initialize content library for account: {$this->getAccount()}");

        $this->getLibrary()->setAccount($this->getAccount())
            ->setPassword($this->configurator->getAccountPassword($this->getAccount()))
            ->setIsDelta(!$this->getIsFull())
            ->setUseDevIndex($this->configurator->useDevIndex($this->getAccount()))
            ->setLanguages($this->configurator->getAccountLanguages($this->getAccount()));
    }

    /**
     * Verifies credentials to the DI
     * If the server is too busy it will trigger a timeout but the export should not be stopped
     */
    protected function verifyCredentials() : void
    {
        $this->logger->info("BoxalinoExporter: verify credentials for account: {$this->getAccount()}");
        try {
            $this->getLibrary()->verifyCredentials();
        } catch(\LogicException $e){
            $this->logger->warning("BoxalinoExporter: verifyCredentials returned a timeout: {$e->getMessage()}");
        } catch (\Throwable $e){
            $this->logger->error("BoxalinoExporter: verifyCredentials failed with exception: {$e->getMessage()}");
        }
    }

    /**
     * @return bool|string
     * @throws \Exception
     */
    protected function prepareXmlConfigurations() : void
    {
        if (!$this->getIsFull())
        {
            return;
        }

        $this->logger->info('BoxalinoExporter: Prepare XML configuration file: ' . $this->getAccount());

        try {
            $this->logger->info('BoxalinoExporter: Push the XML configuration file to the Data Indexing server for account: ' . $this->getAccount());
            $this->getLibrary()->pushDataSpecifications();
        } catch(\LogicException $e){
            $this->logger->warning('BoxalinoExporter: publishing XML configurations returned a timeout: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $value = @json_decode($e->getMessage(), true);
            if (isset($value['error_type_number']) && $value['error_type_number'] == 3) {
                $this->logger->info('BoxalinoExporter: Try to push the XML file a second time, error 3 happens always at the very first time but not after: ' . $this->getAccount());
                $this->getLibrary()->pushDataSpecifications();
            } else {
                $this->logger->error("BoxalinoExporter: pushDataSpecifications failed with exception: " . $e->getMessage() . " If you have attribute changes, please check with Boxalino.");
                throw new \Exception("BoxalinoExporter: pushDataSpecifications failed with exception: " . $e->getMessage());
            }
        }

        $this->logger->info('BoxalinoExporter: Publish the configuration changes from the owner for account: ' . $this->getAccount());
        if($this->configurator->publishConfigurationChanges($this->getAccount()))
        {
            $changes = $this->getLibrary()->publishChanges();
            if (!empty($changes) && sizeof($changes['changes']) > 0) {
                $this->logger->info("BoxalinoExporter: changes in configuration detected and published for account " . $this->getAccount());
            }
            if(isset($changes['token']))
            {
                $this->logger->info("BoxalinoExporter: New token for account {$this->getAccount()} - {$changes['token']}");
            }
        }

        $this->logger->info('BoxalinoExporter: OK - stop waiting for Data Intelligence processing for account: ' . $this->getAccount());
    }

    /**
     * @return array|string
     */
    protected function pushToDI() : void
    {
        $this->logger->info('BoxalinoExporter: pushing the archive to DI for account: ' . $this->getAccount());
        try {
            $this->getLibrary()->pushData($this->configurator->getExportTemporaryArchivePath($this->getAccount()), $this->getTimeout());
        } catch(\LogicException $e){
            $this->logger->warning($e->getMessage());
        }
    }

    /**
     * Exporting products and product elements (tags, manufacturers, category, prices, reviews, etc)
     */
    public function exportProducts() : void
    {
        $this->logger->info("BoxalinoExporter: Preparing products for account {$this->getAccount()}.");
        try{
            $this->productExporter->setAccount($this->getAccount())
                ->setFiles($this->getFiles())
                ->setLibrary($this->getLibrary())
                ->setIsDelta(!$this->getIsFull())
                ->export();
        } catch(\Exception $exc)
        {
            throw $exc;
        }
    }

    /**
     * Export customer data
     */
    public function exportCustomers() : void
    {
        if($this->getIsFull())
        {
            $this->logger->info("BoxalinoExporter: Preparing customers for account {$this->getAccount()}.");
            $this->customerExporter->setFiles($this->getFiles())
                ->setAccount($this->getAccount())
                ->setLibrary($this->productExporter->getLibrary())
                ->export();
        }
    }

    /**
     * Export order data
     */
    public function exportOrders() : void
    {
        if($this->getIsFull())
        {
            $this->logger->info("BoxalinoExporter: Preparing transactions for account {$this->getAccount()}.");
            $this->transactionExporter->setFiles($this->getFiles())
                ->setAccount($this->getAccount())
                ->setLibrary($this->customerExporter->getLibrary())
                ->export();
        }
    }

    /**
     * Add scheduler update for current process
     *
     * @param string $status
     * @throws \Doctrine\DBAL\DBALException
     */
    public function addSchedulerStatus(string $status) : void
    {
        $this->scheduler->updateScheduler(
            date("Y-m-d H:i:s"),
            $this->getType(),
            $status,
            $this->getAccount()
        );
    }

    /**
     * @return bool
     */
    public function getIsFull() : bool
    {
        return $this->isFull;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setIsFull(bool $value)
    {
        $this->isFull = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getDirectory() : string
    {
        return $this->directory;
    }

    /**
     * @param mixed $directory
     * @return ExporterService
     */
    public function setDirectory(string $directory) : self
    {
        $this->directory = $directory;
        return $this;
    }

    /**
     * @param string $value
     * @return ExporterService
     */
    public function setType(string $value) : self
    {
        $this->type = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getType() : string
    {
        return $this->type;
    }

    /**
     * @return FileHandler
     */
    public function getFiles() : FileHandler
    {
        return $this->fileHandler;
    }

    /**
     * @return ContentLibrary
     */
    public function getLibrary() : ContentLibrary
    {
        return $this->library;
    }

    /**
     * @param string $account
     * @return $this
     */
    public function setAccount(string $account)
    {
        $this->account = $account;
        return $this;
    }

    /**
     * @return string
     */
    public function getAccount() : string
    {
        return $this->account;
    }

    /**
     * @param string $id
     * @return ExporterService
     */
    public function setExporterId(string $id) :self
    {
        $this->exporterId = $id;
        return $this;
    }

    /**
     * @param string $timeout
     * @return ExporterService
     */
    public function setTimeout(string $timeout) :self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * @return int
     */
    public function getTimeout() : int
    {
        return $this->timeout;
    }

}
