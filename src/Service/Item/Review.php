<?php
namespace Boxalino\Exporter\Service\Item;

use Boxalino\Exporter\Service\Component\Product;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class Review
 * Exports product votes (average)
 *
 * @package Boxalino\Exporter\Service\Item
 */
class Review extends ItemsAbstract
{

    CONST EXPORTER_COMPONENT_ITEM_NAME = "review_points";
    CONST EXPORTER_COMPONENT_ITEM_MAIN_FILE = 'review_points.csv';
    CONST EXPORTER_COMPONENT_ITEM_RELATION_FILE = 'product_review_points.csv';

    public function export()
    {
        $this->logger->info("BoxalinoExporter: Preparing products - START REVIEW POINTS EXPORT.");
        $this->exportItemRelation();
        $this->logger->info("BoxalinoExporter: Preparing products - END REVIEW POINTS.");
    }

    public function setFilesDefinitions()
    {
        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath($this->getItemRelationFile()), 'product_id');
        $this->getLibrary()->addSourceNumberField($attributeSourceKey, $this->getPropertyName(), $this->getPropertyIdField());
    }

    public function getItemRelationQuery(int $page = 1): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($this->getRequiredFields())
            ->from("product_review")
            ->leftJoin('product_review', 'product', 'product', 'product.id = product_review.product_id OR product.parent_id = product_review.product_id')
            ->andWhere('product_review.product_version_id = :live')
            #->andWhere('product_review.sales_channel_id = :channel') #by default - the channel is not used to filter out reviews
            ->andWhere('product_review.status = 1')
            ->addGroupBy('IFNULL(product.parent_id, product.id)')
            ->setParameter("channel", Uuid::fromHexToBytes($this->getChannelId()), ParameterType::BINARY)
            ->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY)
            ->setFirstResult(($page - 1) * Product::EXPORTER_STEP)
            ->setMaxResults(Product::EXPORTER_STEP);
        
        $productIds = $this->getExportedProductIds();
        if(!empty($productIds))
        {
            $query->andWhere('product.id IN (:ids)')
                ->setParameter('ids', Uuid::fromHexToBytesList($productIds), Connection::PARAM_STR_ARRAY);
        }

        return $query;
    }

    /**
     * @return array
     */
    public function getRequiredFields(): array
    {
        return ["AVG(product_review.points) AS {$this->getPropertyIdField()}", 'IFNULL(LOWER(HEX(product.parent_id)), LOWER(HEX(product.id))) AS product_id'];
    }
}
