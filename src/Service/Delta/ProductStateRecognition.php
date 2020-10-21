<?php
namespace Boxalino\Exporter\Service\Delta;

use Boxalino\Exporter\Service\ExporterScheduler;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Log\LoggerInterface;

/**
 * Class ProductStateRecognition
 * @package Boxalino\Exporter\Service\Delta
 */
class ProductStateRecognition implements ProductStateRecognitionInterface
{

    /**
     * @var ExporterScheduler
     */
    protected $scheduler;

    /**
     * ProductStateRecognition constructor.
     * @param ExporterScheduler $exporterScheduler
     */
    public function __construct(ExporterScheduler $exporterScheduler)
    {
        $this->scheduler = $exporterScheduler;
    }

    /**
     * Default: export the products updated since last delta run and exclude the new products
     * 
     * @param QueryBuilder $query
     * @return QueryBuilder
     */
    public function addState(QueryBuilder $query): QueryBuilder
    {
       return $query->andWhere("STR_TO_DATE(p.updated_at,  '%Y-%m-%d %H:%i') > :lastExport OR STR_TO_DATE(parent.updated_at,  '%Y-%m-%d %H:%i') > :lastExport")
           ->andWhere("STR_TO_DATE(p.created_at,  '%Y-%m-%d %H:%i') < :lastExport OR STR_TO_DATE(parent.created_at,  '%Y-%m-%d %H:%i') < :lastExport")
           ->setParameter('lastExport', $this->getLastExport());
    }

    /**
     * @return string
     */
    public function getLastExport()
    {
        if (empty($this->lastExport))
        {
            $this->lastExport = date("Y-m-d H:i", strtotime("-1 day"));
            $latestExport = $this->scheduler->getLastExportByAccountStatus($this->getAccount(), ExporterScheduler::BOXALINO_EXPORTER_STATUS_SUCCESS);
            if(!is_null($latestExport))
            {
                $this->lastExport = $latestExport;
            }
        }

        return $this->lastExport;
    }

    /**
     * @param string $account
     * @return $this
     */
    public function setAccount(string $account) : self
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

}
