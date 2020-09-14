<?php
namespace Boxalino\Exporter\ScheduledTask;

use Boxalino\Exporter\Service\ExporterDeltaInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * Class ExportFullHandler
 * @package Boxalino\Exporter\ScheduledTask
 */
abstract class ExporterDeltaHandlerAbstract extends ScheduledTaskHandler
{
    /**
     * @var ExporterDeltaInterface
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

    /**
     * @var array
     */
    protected $ids = [];

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        LoggerInterface $logger,
        ExporterDeltaInterface $exporter
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
     * Triggers the delta data exporter for a specific account if so it is set
     *
     * @throws \Exception
     */
    public function run(): void
    {
        if(!is_null($this->account))
        {
            $this->exporter->setAccount($this->account);
        }

        if(!empty($this->ids))
        {
            $this->exporter->setIds($this->ids);
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

    /**
     * Sets product IDs
     *
     * @param array $ids
     * @return $this
     */
    public function setIds(array $ids)
    {
        $this->ids = $ids;
        return $this;
    }

}
