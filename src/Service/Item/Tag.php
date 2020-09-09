<?php
namespace Boxalino\Exporter\Service\Item;

use Boxalino\Exporter\Service\Component\Product;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class Tag
 * @package Boxalino\Exporter\Service\Item
 */
class Tag extends ItemsAbstract
{
    CONST EXPORTER_COMPONENT_ITEM_NAME = "tag";
    CONST EXPORTER_COMPONENT_ITEM_MAIN_FILE = 'tag.csv';
    CONST EXPORTER_COMPONENT_ITEM_RELATION_FILE = 'product_tag.csv';

    public function export()
    {
        $this->logger->info("BoxalinoExporter: Preparing products - START TAG EXPORT.");
        $query = $this->connection->createQueryBuilder();
        $query->select(["LOWER(HEX(id)) AS {$this->getPropertyIdField()}", "name AS value"])
            ->from("tag");

        $count = $query->execute()->rowCount();
        if($count == 0)
        {
            $headers = [[$this->getPropertyIdField(), "value"]];
            $this->getFiles()->savePartToCsv($this->getItemMainFile(), $headers);
        } else {
            $data = $query->execute()->fetchAll();
            $data = array_merge(array(array_keys(end($data))), $data);
            $this->getFiles()->savePartToCsv($this->getItemMainFile(), $data);
        }

        $this->exportItemRelation();
        $this->logger->info("BoxalinoExporter: Preparing products - END TAG.");
    }

    /**
     * @param int $page
     * @return QueryBuilder
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    public function getItemRelationQuery(int $page = 1) : QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($this->getRequiredFields())
            ->from('product_tag')
            ->leftJoin('product_tag', 'product', 'product', 'product_tag.product_id = product.id AND product_tag.product_version_id=product.version_id')
            ->andWhere('product.version_id = :live')
            ->andWhere("JSON_SEARCH(product.category_tree, 'one', :channelRootCategoryId) IS NOT NULL")
            ->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY)
            ->setParameter('channelRootCategoryId', $this->getRootCategoryId(), ParameterType::STRING)
            ->setFirstResult(($page - 1) * Product::EXPORTER_STEP)
            ->setMaxResults(Product::EXPORTER_STEP);

        $productIds = $this->getExportedProductIds();
        if(!empty($productIds))
        {
            $query->andWhere('product_tag.product_id IN (:ids)')
                ->setParameter('ids', Uuid::fromHexToBytesList($productIds), Connection::PARAM_STR_ARRAY);
        }

        return $query;
    }

    public function setFilesDefinitions() : void
    {
        $optionSourceKey = $this->getLibrary()->addResourceFile($this->getFiles()->getPath($this->getItemMainFile()), $this->getPropertyIdField(), ['value']);
        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath($this->getItemRelationFile()), 'product_id');
        $this->getLibrary()->addSourceStringField($attributeSourceKey, $this->getPropertyName(), $this->getPropertyIdField(), $optionSourceKey);
    }

    public function getRequiredFields(): array
    {
        return ['LOWER(HEX(product_tag.product_id)) AS product_id', "LOWER(HEX(product_tag.tag_id)) AS {$this->getPropertyIdField()}"];
    }
}
