<?php
namespace  Boxalino\Exporter\ScheduledTask;

use Boxalino\Exporter\Service\ExporterFull as FullDataExporter;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * Class ExportFullHandler
 * @package Boxalino\Exporter\ScheduledTask
 */
abstract class ExporterFullHandlerAbstract extends ScheduledTaskHandler
{
    /**
     * @var FullDataExporter
     */
    protected $exporterFull;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $account = null;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        LoggerInterface $logger,
        FullDataExporter $fullExporter
    ){
        parent::__construct($scheduledTaskRepository);
        $this->exporterFull = $fullExporter;
        $this->logger = $logger;
    }

    /**
     * Set the class with the scheduled task configuration
     *
     * @return iterable
     */
    abstract static function getHandledMessages(): iterable;

    /**
     * Triggers the full data exporter for a specific account if so it is set
     *
     * @throws \Exception
     */
    public function run(): void
    {
        if(!is_null($this->account))
        {
            $this->exporterFull->setAccount($this->account);
        }
        try{
            $this->exporterFull->export();
        } catch (\Exception $exc)
        {
            $this->logger->error($exc->getMessage());
            throw $exc;
        }
    }

    /**
     * Sets an account via XML declaration
     *
     * @param string $account
     * @return $this
     */
    public function setAccount(string $account)
    {
        $this->account = $account;
        return $this;
    }

}
