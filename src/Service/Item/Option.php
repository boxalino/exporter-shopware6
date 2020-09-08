<?php
namespace Boxalino\Exporter\Service\Item;

use Boxalino\Exporter\Service\Component\Product;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class Option
 * @package Boxalino\Exporter\Service\Item
 */
class Option extends PropertyTranslation
{

    CONST EXPORTER_COMPONENT_ITEM_NAME = "option_ids_label";
    CONST EXPORTER_COMPONENT_ITEM_MAIN_FILE = 'option_ids_label.csv';
    CONST EXPORTER_COMPONENT_ITEM_RELATION_FILE = 'product_option_ids_label.csv';

    public function export()
    {
        $this->logger->info("BoxalinoExporter: Preparing products - START PRODUCT OPTIONS EXPORT.");
        $this->exportItemRelation();
        $this->logger->info("BoxalinoExporter: Preparing products - END OPTIONS.");
    }

    public function getItemRelationQuery(int $page = 1): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($this->getRequiredFields())
            ->from("product_option")
            ->leftJoin('product_option', '( ' . $this->getLocalizedFieldsQuery()->__toString() . ') ',
                'translation', 'translation.property_group_option_id = product_option.property_group_option_id')
            ->andWhere($this->getLanguageHeaderConditional())
            ->andWhere('product_option.product_version_id = :live')
            ->addGroupBy('product_option.product_id')
            ->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY)
            ->setFirstResult(($page - 1) * Product::EXPORTER_STEP)
            ->setMaxResults(Product::EXPORTER_STEP);

        return $query;
    }

    public function getItemRelationHeaderColumns(array $additionalFields = []): array
    {
        return [array_merge($this->getLanguageHeaders(), ['product_id'])];
    }

    public function setFilesDefinitions()
    {
        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath($this->getItemRelationFile()), 'product_id');
        $this->getLibrary()->addSourceLocalizedTextField($attributeSourceKey, $this->getPropertyName(), $this->getLanguageHeaders());
    }

    public function getRequiredFields(): array
    {
        $translationFields = [];
        foreach($this->getLanguageHeaders() as $languageHeader)
        {
            $translationFields[] = "GROUP_CONCAT(translation.{$languageHeader} SEPARATOR ' - ') AS {$languageHeader}";
        }
        return array_merge($translationFields,
        [
            "LOWER(HEX(product_option.product_id)) AS product_id"
        ]);
    }

}
