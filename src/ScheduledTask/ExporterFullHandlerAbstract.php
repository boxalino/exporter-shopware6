<?php
namespace Boxalino\Exporter\ScheduledTask;

use Boxalino\Exporter\Service\ExporterFullInterface;
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
     * @var ExporterFullInterface
     */
    protected $exporter;

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
        ExporterFullInterface $exporter
    ){
        parent::__construct($scheduledTaskRepository);
        $this->exporter = $exporter;
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
            $this->exporter->setAccount($this->account);
        }
        try{
            $this->exporter->export();
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
