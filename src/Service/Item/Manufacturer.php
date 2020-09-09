<?php
namespace Boxalino\Exporter\Service\Item;

use Boxalino\Exporter\Service\Component\Product;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\Connection;

class Manufacturer extends ItemsAbstract
{

    CONST EXPORTER_COMPONENT_ITEM_NAME = "brand";
    CONST EXPORTER_COMPONENT_ITEM_MAIN_FILE = 'brand.csv';
    CONST EXPORTER_COMPONENT_ITEM_RELATION_FILE = 'product_brands.csv';

    public function export()
    {
        $this->logger->info("BoxalinoExporter: Preparing products - START MANUFACTURER EXPORT.");
        $fields = array_merge($this->getLanguageHeaders(),
            ["LOWER(HEX(manufacturer.product_manufacturer_id)) AS {$this->getPropertyIdField()}"]
        );

        $query = $this->connection->createQueryBuilder();
        $query->select($fields)
            ->from('( ' . $this->getLocalizedFieldsQuery()->__toString() . ')', 'manufacturer')
            ->andWhere('manufacturer.product_manufacturer_version_id = :live')
            ->addGroupBy('manufacturer.product_manufacturer_id')
            ->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY);

        $count = $query->execute()->rowCount();
        if ($count == 0) {
            $headers = [array_merge($this->getLanguageHeaders(), [$this->getPropertyIdField()])];
            $this->getFiles()->savePartToCsv($this->getItemMainFile(), $headers);
        } else {
            $data = $query->execute()->fetchAll();
            $data = array_merge(array(array_keys(end($data))), $data);
            $this->getFiles()->savePartToCsv($this->getItemMainFile(), $data);
        }

        $this->exportItemRelation();
        $this->logger->info("BoxalinoExporter: Preparing products - END MANUFACTURER.");
    }

    /**
     * @param int $page
     * @return QueryBuilder
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    public function getItemRelationQuery(int $page = 1): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($this->getRequiredFields())
            ->from('product', 'p')
            ->leftJoin("p", 'product', 'parent',
                'p.parent_id = parent.id AND p.parent_version_id = parent.version_id')
            ->andWhere('p.version_id = :live')
            ->andWhere('p.product_manufacturer_version_id = :live')
            ->andWhere("JSON_SEARCH(p.category_tree, 'one', :channelRootCategoryId) IS NOT NULL")
            ->addGroupBy('p.id')
            ->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY)
            ->setParameter('channelRootCategoryId', $this->getRootCategoryId(), ParameterType::STRING)
            ->setFirstResult(($page - 1) * Product::EXPORTER_STEP)
            ->setMaxResults(Product::EXPORTER_STEP);

        $productIds = $this->getExportedProductIds();
        if(!empty($productIds))
        {
            $query->andWhere('p.id IN (:ids)')
                ->setParameter('ids', Uuid::fromHexToBytesList($productIds), Connection::PARAM_STR_ARRAY);
        }

        return $query;
    }

    /**
     * @throws \Exception
     */
    public function setFilesDefinitions()
    {
        $optionSourceKey = $this->getLibrary()->addResourceFile($this->getFiles()->getPath($this->getItemMainFile()),
            $this->getPropertyIdField(), $this->getLanguageHeaders());
        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath($this->getItemRelationFile()), 'product_id');
        $this->getLibrary()->addSourceLocalizedTextField($attributeSourceKey, $this->getPropertyName(),$this->getPropertyIdField(), $optionSourceKey);
    }

    /**
     * @return QueryBuilder
     * @throws \Exception
     */
    public function getLocalizedFieldsQuery() : QueryBuilder
    {
        return $this->getLocalizedFields("product_manufacturer_translation", 'product_manufacturer_id',
            'product_manufacturer_id', 'product_manufacturer_version_id', 'name',
            ['product_manufacturer_translation.product_manufacturer_id', 'product_manufacturer_translation.product_manufacturer_version_id']
        );
    }

    /**
     * @return array
     */
    public function getRequiredFields(): array
    {
        return ['LOWER(HEX(p.id)) AS product_id',
            "IF(p.product_manufacturer_id IS NULL, LOWER(HEX(parent.product_manufacturer_id)), LOWER(HEX(p.product_manufacturer_id))) AS {$this->getPropertyIdField()}"
        ];
    }
}
