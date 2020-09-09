<?php
namespace Boxalino\Exporter\Service\Item;

use Boxalino\Exporter\Service\Component\Product;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class Property
 * check src/Core/Content/Property/PropertyGroupDefinition.php for other property types and definitions
 * Exports all product-property relations which assign filterable information such as size or color to the product
 *
 * @package Boxalino\Exporter\Service\Item
 */
class Property extends PropertyTranslation
{
    /**
     * @var string
     */
    protected $property;


    public function export()
    {
        $this->logger->info("BoxalinoExporter: Preparing products - START PROPERTIES EXPORT.");
        $properties = $this->getPropertyNames();
        foreach($properties as $property)
        {
            $this->setProperty($property['name']); $this->setPropertyId($property['property_group_id']);
            $this->logger->info("BoxalinoExporter: Preparing products - START $this->property EXPORT.");
            $totalCount = 0; $page = 1; $data=[]; $header = true;
            while (Product::EXPORTER_LIMIT > $totalCount + Product::EXPORTER_STEP)
            {
                $query = $this->getLocalizedPropertyQuery($page);
                $count = $query->execute()->rowCount();
                $totalCount += $count;
                if ($totalCount == 0) {
                    if ($page == 1) {
                        $this->logger->info("BoxalinoExporter: PRODUCTS EXPORT FACETS: No options found for $this->property.");
                        $headers = $this->getMainHeaderColumns();
                        $this->getFiles()->savePartToCsv($this->getItemMainFile(), $headers);
                    }
                    break;
                }
                $data = $query->execute()->fetchAll();
                if ($header) {
                    $header = false;
                    $data = array_merge(array(array_keys(end($data))), $data);
                }

                foreach(array_chunk($data, Product::EXPORTER_DATA_SAVE_STEP) as $dataSegment)
                {
                    $this->getFiles()->savePartToCsv($this->getItemMainFile(), $dataSegment);
                }

                $data = []; $page++;
                if($count < Product::EXPORTER_STEP - 1) { break;}
            }

            $this->exportItemRelation();
            $this->logger->info("BoxalinoExporter: Preparing products - END $this->property.");
        }

        $this->logger->info("BoxalinoExporter: Preparing products - END PROPERTIES.");
    }

    /**
     * @throws \Exception
     */
    public function setFilesDefinitions()
    {
        $optionSourceKey = $this->getLibrary()->addResourceFile($this->getFiles()->getPath($this->getItemMainFile()),
            $this->getPropertyIdField(), $this->getLanguageHeaders());
        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath($this->getItemRelationFile()), 'product_id');
        $this->getLibrary()->addSourceLocalizedTextField($attributeSourceKey, $this->getPropertyName(), $this->getPropertyIdField(), $optionSourceKey);
    }

    /**
     * @param int $page
     * @return QueryBuilder
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    public function getItemRelationQuery(int $page = 1): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            "LOWER(HEX(product_property.product_id)) AS product_id",
            "LOWER(HEX(product_property.property_group_option_id)) AS '{$this->getPropertyIdField()}'"])
            ->from("product_property")
            ->leftJoin("product_property", "property_group_option", "property_group_option",
                "product_property.property_group_option_id = property_group_option.id")
            ->where("property_group_option.property_group_id = :propertyId")
            ->setParameter("propertyId", Uuid::fromHexToBytes($this->propertyId), ParameterType::STRING)
            ->setFirstResult(($page - 1) * Product::EXPORTER_STEP)
            ->setMaxResults(Product::EXPORTER_STEP);

        $productIds = $this->getExportedProductIds();
        if(!empty($productIds))
        {
            $query->andWhere('product_property.product_id IN (:ids)')
                ->setParameter('ids', Uuid::fromHexToBytesList($productIds), Connection::PARAM_STR_ARRAY);
        }

        return $query;
    }

    /**
     * @return string
     */
    public function getPropertyName() : string
    {
        return $this->property;
    }

    /**
     * @return string
     */
    public function getItemMainFile() : string
    {
        return "{$this->property}.csv";
    }

    /**
     * @param string $property
     * @return $this
     */
    protected function setProperty(string $property)
    {
        $this->property = strtolower(preg_replace("/[\W]+/", '_', iconv('utf-8', 'ascii//TRANSLIT',$property)));
        return $this;
    }

    /**
     * @return string
     */
    public function getItemRelationFile() : string
    {
        return "property_{$this->property}.csv";
    }

}
