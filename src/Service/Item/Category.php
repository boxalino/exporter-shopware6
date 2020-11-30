<?php
namespace Boxalino\Exporter\Service\Item;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class Category
 * Exports product-category relations and category information
 *
 * @package Boxalino\Exporter\Service\Item
 */
class Category extends ItemsAbstract
{
    CONST EXPORTER_COMPONENT_ITEM_NAME = "category";
    CONST EXPORTER_COMPONENT_ITEM_MAIN_FILE = 'categories.csv';
    CONST EXPORTER_COMPONENT_ITEM_RELATION_FILE = 'product_categories.csv';

    /**
     * Export item categories
     * REQUIRED FIELDS are: id, parent_id and translation per language
     * ADITIONAL DETAILS exported are: tags
     */
    public function export()
    {
        $this->logger->info("BoxalinoExporter: Preparing products - START CATEGORIES EXPORT.");
        $this->config->setAccount($this->getAccount());
        $rootCategoryId = $this->getRootCategoryId();
        $query = $this->connection->createQueryBuilder();
        $query->select($this->getRequiredFields())
            ->from("category")
            ->leftJoin('category', '( ' . $this->getLocalizedFieldsQuery()->__toString() . ') ',
                'translations', 'translations.category_id = category.id AND category.version_id = translations.category_version_id')
            ->leftJoin('category', '( ' . $this->getTagsQuery()->__toString() . ' )', 'category_tags',
                'category_tags.category_id = category.id AND category_tags.category_version_id = category.version_id')
            ->andWhere('category.path LIKE :rootCategoryId OR LOWER(HEX(category.id))=:root')
            ->andWhere('category.version_id = :live')
            ->setParameter('rootCategoryId', "%|$rootCategoryId|%", ParameterType::STRING)
            ->setParameter('root', $rootCategoryId, ParameterType::STRING)
            ->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY);

        $data = $query->execute()->fetchAll();
        $data = array_merge(array(array_keys(end($data))), $data);
        $this->getFiles()->savePartToCsv($this->getItemMainFile(), $data);
        $this->getLibrary()->addCategoryFile($this->getFiles()->getPath($this->getItemMainFile()), 'id', 'parent_id', $this->getLanguageHeaders());

        $this->exportItemRelation();
        $this->logger->info("BoxalinoExporter: Preparing products - END CATEGORIES.");
    }

    /**
     * Accessing category name translation (name)
     * If there is no translation available, the default one is used
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     * @throws \Exception
     */
    protected function getLocalizedFieldsQuery() : QueryBuilder
    {
        return $this->getLocalizedFields('category_translation', 'category_id', 'category_id',
            'category_version_id','name',
            ['category_translation.category_id', 'category_translation.category_version_id']
        );
    }

    /**
     * Returning category tags joined via comma
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    public function getTagsQuery() : QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            "category_tag.category_id",
            "category_tag.category_version_id",
            "GROUP_CONCAT(tag.name SEPARATOR ',') as tags"
        ])
            ->from('category_tag')
            ->leftJoin('category_tag', 'tag', 'tag', 'category_tag.tag_id = tag.id')
            ->groupBy(["category_tag.category_id", "category_tag.category_version_id"]);

        return $query;
    }

    /**
     * @TODO implement incremental save to file for data
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    public function exportItemRelation()
    {
        $this->logger->info("BoxalinoExporter: Preparing products - START CATEGORIES RELATION EXPORT.");

        $query = $this->getItemRelationQuery();
        $data = $query->execute()->fetchAll();
        if (count($data) > 0) {
            $data = array_merge(array(array_keys(end($data))), $data);
            $this->getFiles()->savePartToCsv($this->getItemRelationFile(), $data);
        } else {
            $headers = $this->getItemRelationHeaderColumns();
            $this->getFiles()->savePartToCsv($this->getItemRelationFile(), $headers);
        }

        $this->setFilesDefinitions();
        $this->logger->info("BoxalinoExporter: Preparing products - END CATEGORY RELATION EXPORT.");
    }

    public function setFilesDefinitions()
    {
        $productToCategoriesSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath($this->getItemRelationFile()), 'product_id');
        $this->getLibrary()->setCategoryField($productToCategoriesSourceKey, $this->getPropertyIdField());
    }

    /**
     * @param int $page
     * @return QueryBuilder
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    public function getItemRelationQuery(int $page=1): QueryBuilder
    {
        $rootCategoryId = $this->getRootCategoryId();
        $query = $this->connection->createQueryBuilder();
        $query->select(['LOWER(HEX(product_category_tree.category_id)) AS category_id', 'LOWER(HEX(product_category_tree.product_id)) AS product_id'])
            ->from('product_category_tree')
            ->leftJoin('product_category_tree', 'category', 'category',
                'product_category_tree.category_id = category.id AND product_category_tree.category_version_id = category.version_id'
            )
            ->andWhere('category.path LIKE :rootCategoryId OR LOWER(HEX(category.id))=:root')
            ->andWhere('category.version_id = :live')
            ->andWhere('product_category_tree.product_version_id = :live')
            ->setParameter('rootCategoryId', "%|$rootCategoryId|%", ParameterType::STRING)
            ->setParameter('root', $rootCategoryId, ParameterType::STRING)
            ->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY);

        $productIds = $this->getExportedProductIds();
        if (!empty($productIds)) 
        {
            $query->andWhere("product_category_tree.product_id IN (:ids)")
                ->setParameter('ids', Uuid::fromHexToBytesList($productIds), Connection::PARAM_STR_ARRAY);
        }

        return $query;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getRequiredFields(): array
    {
        $translationFields = preg_filter('/^/', 'translations.', $this->getLanguageHeaders());
        return array_merge($translationFields,
            [
                'LOWER(HEX(category.id)) AS id', 'category.auto_increment', 'LOWER(HEX(category.parent_id)) as parent_id',
                'LOWER(HEX(category.cms_page_id)) AS cms_page_id',
                'category.level', 'category.active', 'category.child_count', 'category.visible', 'category.type',
                'category.display_nested_products', 'category.created_at', 'category.updated_at',
                'category_tags.tags'
            ]
        );
    }

}
