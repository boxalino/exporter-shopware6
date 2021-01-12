<?php
namespace Boxalino\Exporter\Service\Component;

use Boxalino\Exporter\Service\Component\ProductComponentInterface;
use Boxalino\Exporter\Service\ExporterScheduler;
use Boxalino\Exporter\Service\Item\Image;
use Boxalino\Exporter\Service\Item\ItemsAbstract;
use Boxalino\Exporter\Service\Item\Manufacturer;
use Boxalino\Exporter\Service\Item\Category;
use Boxalino\Exporter\Service\Item\Media;
use Boxalino\Exporter\Service\Item\Option;
use Boxalino\Exporter\Service\Item\Price;
use Boxalino\Exporter\Service\Item\PriceAdvanced;
use Boxalino\Exporter\Service\Item\Property;
use Boxalino\Exporter\Service\Item\Translation;
use Boxalino\Exporter\Service\Item\Url;
use Boxalino\Exporter\Service\Item\Review;
use Boxalino\Exporter\Service\Item\Visibility;
use Boxalino\Exporter\Service\Delta\ProductStateRecognitionInterface;
use Boxalino\Exporter\Service\ExporterConfigurationInterface;
use Boxalino\Exporter\Service\Item\Tag;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Google\Auth\Cache\Item;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class Product
 * Product component exporting logic
 *
 * @package Boxalino\Exporter\Service\Component
 */
class Product extends ExporterComponentAbstract
    implements ProductComponentInterface
{
    protected $lastExport;
    protected $exportedProductIds = [];
    protected $deltaIds = [];

    /**
     * @var Category
     */
    protected $categoryExporter;

    /**
     * @var Property
     */
    protected $facetExporter;

    /**
     * @var Option
     */
    protected $optionExporter;

    /**
     * @var Media
     */
    protected $mediaExporter;

    /**
     * @var Image
     */
    protected $imagesExporter;

    /**
     * @var Manufacturer
     */
    protected $manufacturerExporter;

    /**
     * @var Price
     */
    protected $priceExporter;

    /**
     * @var PriceAdvanced
     */
    protected $priceAdvancedExporter;

    /**
     * @var Url
     */
    protected $urlExporter;

    /**
     * @var Review
     */
    protected $reviewsExporter;

    /**
     * @var Translation
     */
    protected $translationExporter;

    /**
     * @var Tag
     */
    protected $tagExporter;

    /**
     * @var Visibility
     */
    protected $visibilityExporter;

    /**
     * @var \ArrayObject
     */
    protected $itemExportersList;

    /**
     * @var ProductStateRecognitionInterface
     */
    protected $deltaStateRecognitionHandler;

    public function __construct(
        ComponentResource $resource,
        Connection $connection,
        LoggerInterface $boxalinoLogger,
        ExporterConfigurationInterface $exporterConfigurator,
        Category $categoryExporter,
        Property $facetExporter,
        Option $optionExporter,
        Media $mediaExporter,
        Image $imagesExporter,
        Manufacturer $manufacturerExporter,
        Price $priceExporter,
        PriceAdvanced $priceAdvanced,
        Url $urlExporter,
        Review $reviewsExporter,
        Translation $translationExporter,
        Tag $tagExporter,
        Visibility $visibilityExporter,
        ProductStateRecognitionInterface $recognition
    ){
        $this->itemExportersList = new \ArrayObject();
        $this->optionExporter = $optionExporter;
        $this->priceAdvancedExporter = $priceAdvanced;
        $this->categoryExporter = $categoryExporter;
        $this->facetExporter = $facetExporter;
        $this->mediaExporter = $mediaExporter;
        $this->imagesExporter = $imagesExporter;
        $this->manufacturerExporter = $manufacturerExporter;
        $this->priceExporter = $priceExporter;
        $this->urlExporter = $urlExporter;
        $this->reviewsExporter = $reviewsExporter;
        $this->translationExporter = $translationExporter;
        $this->tagExporter = $tagExporter;
        $this->visibilityExporter = $visibilityExporter;
        $this->deltaStateRecognitionHandler = $recognition;

        parent::__construct($resource, $connection, $boxalinoLogger, $exporterConfigurator);
    }

    public function exportComponent()
    {
        /** defaults */
        $this->config->setAccount($this->getAccount());
        $header = true; $data = []; $totalCount = 0; $page = 1; $exportFields=[]; $startExport = microtime(true);
        $this->logger->info("BoxalinoExporter: Preparing products - MAIN.");
        $properties = $this->getFields();
        $rootCategoryId = $this->config->getChannelRootCategoryId();
        $defaultLanguageId = $this->config->getChannelDefaultLanguageId();

        while (ProductComponentInterface::EXPORTER_LIMIT > $totalCount + ProductComponentInterface::EXPORTER_STEP)
        {
            $this->logger->info("BoxalinoExporter: Products export - OFFSET " . $totalCount);
            $query = $this->connection->createQueryBuilder();
            $query->select($properties)
                ->from('product', 'p')
                ->leftJoin("p", 'product', 'parent',
                    'p.parent_id = parent.id AND p.parent_version_id = parent.version_id')
                ->leftJoin('p', 'tax', 'tax', 'tax.id = p.tax_id')
                ->leftJoin('p', 'delivery_time_translation', 'delivery_time_translation',
                    'p.delivery_time_id = delivery_time_translation.delivery_time_id AND delivery_time_translation.language_id = :defaultLanguage')
                ->leftJoin('p', 'unit_translation', 'unit_translation', 'unit_translation.unit_id = p.unit_id AND unit_translation.language_id = :defaultLanguage')
                ->leftJoin('p', 'currency', 'currency', "JSON_UNQUOTE(JSON_EXTRACT(JSON_EXTRACT(p.price, '$.*.currencyId'),'$[0]')) = LOWER(HEX(currency.id))")
                ->andWhere('p.version_id = :live')
                ->andWhere("JSON_SEARCH(p.category_tree, 'one', :channelRootCategoryId) IS NOT NULL")
                ->addGroupBy('p.id')
                ->orderBy('p.created_at', 'DESC')
                ->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY)
                ->setParameter('channelRootCategoryId', $rootCategoryId, ParameterType::STRING)
                ->setParameter('defaultLanguage', Uuid::fromHexToBytes($defaultLanguageId), ParameterType::BINARY)
                ->setFirstResult(($page - 1) * ProductComponentInterface::EXPORTER_STEP)
                ->setMaxResults(ProductComponentInterface::EXPORTER_STEP);

            if ($this->getIsDelta())
            {
                $query = $this->addDeltaStateRecognition($query);
            }

            $count = $query->execute()->rowCount();
            $totalCount+=$count;
            if($totalCount == 0)
            {
                break; #return false;
            }
            $results = $this->processExport($query);
            foreach($results as $row)
            {
                if($this->getIsDelta())
                {
                    $this->exportedProductIds[] = $row['id'];
                }
                $row['purchasable'] = $this->getProductPurchasableValue($row);
                $row['immediate_delivery'] = $this->getProductImmediateDeliveryValue($row);
                $row['show_out_of_stock'] = (string)!$row['is_closeout'];
                $row['is_new'] = $this->getIsNew();
                $row['in_sales'] = $this->getInSales();
                if($header)
                {
                    $exportFields = array_keys($row); $this->setHeaderFields($exportFields); $data[] = $exportFields; $header = false;
                }
                $data[] = $row;
                if(count($data) > ProductComponentInterface::EXPORTER_DATA_SAVE_STEP)
                {
                    $this->getFiles()->savePartToCsv($this->getComponentMainFile(), $data);
                    $data = [];
                }
            }

            $this->getFiles()->savePartToCsv($this->getComponentMainFile(), $data);
            $data = []; $page++;
            if($count < ProductComponentInterface::EXPORTER_STEP - 1) { break;}
        }

        $endExport =  (microtime(true) - $startExport) * 1000;
        $this->logger->info("BoxalinoExporter: MAIN PRODUCT DATA EXPORT FOR $totalCount PRODUCTS TOOK: $endExport ms, memory (MB): "
            . round(memory_get_usage(true)/1048576,2)
        );
        if($totalCount == 0)
        {
            $this->logger->info("BoxalinoExporter: NO PRODUCTS FOUND.");
            $this->setSuccessOnComponentExport(false);
            return $this;
        }

        $this->defineProperties($exportFields);

        $this->logger->info("BoxalinoExporter: -- Main product after memory: ". round(memory_get_usage(true)/1048576,2));
        $this->logger->info("BoxalinoExporter: Finished products - main.");

        $this->setSuccessOnComponentExport(true);
        $this->exportItems();
    }

    /**
     * Export other product elements and properties (categories, translations, etc)
     *
     * @return void
     * @throws \Exception
     */
    public function exportItems() : void
    {
        if (!$this->getSuccessOnComponentExport())
        {
            return;
        }

        $this->_exportExtra("categories", $this->categoryExporter);
        $this->_exportExtra("translations", $this->translationExporter);
        $this->_exportExtra("manufacturers", $this->manufacturerExporter);
        $this->_exportExtra("prices", $this->priceExporter);
        $this->_exportExtra("advancedPrices", $this->priceAdvancedExporter);
        $this->_exportExtra("reviews", $this->reviewsExporter);
        $this->_exportExtra("tags", $this->tagExporter);
        $this->_exportExtra("visibility", $this->visibilityExporter);
        $this->_exportExtra("image", $this->imagesExporter);

        if ($this->config->exportProductImages())
        {
            $this->_exportExtra("media", $this->mediaExporter);
        }

        if ($this->config->exportProductUrl())
        {
            $this->_exportExtra("urls", $this->urlExporter);
        }

        $this->_exportExtra("facets", $this->facetExporter);
        $this->_exportExtra("options", $this->optionExporter);

        /** @var ItemsAbstract $itemExporter */
        foreach($this->itemExportersList as $itemExporter)
        {
            $this->_exportExtra($itemExporter->getPropertyName(), $itemExporter);
        }
    }

    /**
     * Contains the logic for exporting individual items describing the product component
     * (categories, translations, prices, reviews, etc..)
     * @param $step
     * @param $exporter
     */
    protected function _exportExtra($step, $exporter)
    {
        try {
            $this->logger->info("BoxalinoExporter: Preparing products - {$step}.");
            $exporter->setAccount($this->getAccount())
                ->setFiles($this->getFiles())
                ->setLibrary($this->getLibrary())
                ->setExportedProductIds($this->getIds());
            $exporter->export();
            $this->logger->info("BoxalinoExporter: MEMORY (MB) AFTER {$step}: " . round(memory_get_usage(true)/1048576,2));
        } catch (\Exception $exception)
        {
            $this->logger->info("BoxalinoExporterError: There was an error during the {$step} export. The product export will continue to next step.");
            $this->logger->warning("BoxalinoExporterError: There was an occurance during the {$step} export: {$exception->getMessage()}");
        }
    }

    /**
     * @param array $properties
     * @return void
     * @throws \Exception
     */
    public function defineProperties(array $properties) : void
    {
        $mainSourceKey = $this->getLibrary()->addMainCSVItemFile($this->getFiles()->getPath($this->getComponentMainFile()), $this->getComponentIdField());
        $this->getLibrary()->addSourceStringField($mainSourceKey, 'bx_purchasable', 'purchasable');
        $this->getLibrary()->addSourceStringField($mainSourceKey, 'immediate_delivery', 'immediate_delivery');
        $this->getLibrary()->addSourceStringField($mainSourceKey, 'bx_type', $this->getComponentIdField());
        $this->getLibrary()->addFieldParameter($mainSourceKey, 'bx_type', 'pc_fields', '"product"  AS final_value');
        $this->getLibrary()->addFieldParameter($mainSourceKey, 'bx_type', 'multiValued', 'false');

        foreach ($properties as $property)
        {
            if ($property == $this->getComponentIdField()) {
                continue;
            }

            if (in_array($property, $this->getNumberFields())) {
                $this->getLibrary()->addSourceNumberField($mainSourceKey, $property, $property);
                $this->getLibrary()->addFieldParameter($mainSourceKey, $property, 'multiValued', 'false');
                continue;
            }

            $this->getLibrary()->addSourceStringField($mainSourceKey, $property, $property);
            if (in_array($property, $this->getSingleValuedFields()))
            {
                $this->getLibrary()->addFieldParameter($mainSourceKey, $property, 'multiValued', 'false');
            }
        }
    }

    /**
     * @return array|string[]
     */
    public function getNumberFields() : array
    {
        return ["available_stock", "stock", "rating_average", "child_count", "purchasable", "immediate_delivery"];
    }

    /**
     * @return array|string[]
     */
    public function getSingleValuedFields() : array
    {
        return [
            "parent_id", "release_date", "created_at", "updated_at", "product_number", "manufacturer_number", "ean",
            "group_id", "mark_as_topseller", "visibility", "shipping_free", "is_closeout", "immediate_delivery",
            "show_out_of_stock", "is_new", "is_sale", "min_purchase", "purchase_steps", "available"
        ];
    }

    /**
     * Getting a list of product attributes and the table it comes from
     * To be used in the general SQL select
     *
     * @return array
     * @throws \Exception
     */
    public function getFields() : array
    {
        return $this->getRequiredProperties();
    }

    /**
     * In order to ensure transparency for the CDAP integration
     *
     * @return array
     */
    public function getRequiredProperties(): array
    {
        return [
            'LOWER(HEX(p.id)) AS id', 'p.auto_increment', 'p.product_number', 'p.active', 'LOWER(HEX(p.parent_id)) AS parent_id',
            'IF(p.parent_id IS NULL, p.active, parent.active) AS bx_parent_active',
            'LOWER(HEX(p.tax_id)) AS tax_id',
            'LOWER(HEX(p.delivery_time_id)) AS delivery_time_id', 'LOWER(HEX(p.product_media_id)) AS product_media_id',
            'LOWER(HEX(p.cover)) AS cover', 'LOWER(HEX(p.unit_id)) AS unit_id', 'p.category_tree', 'p.option_ids',
            'p.property_ids',
            'IF(p.parent_id IS NULL, p.manufacturer_number, IF(p.manufacturer_number IS NULL, parent.manufacturer_number, p.manufacturer_number)) AS manufactuere_number',
            'IF(p.parent_id IS NULL, p.ean, IF(p.ean IS NULL, parent.ean, p.ean)) AS ean',
            'p.stock', 'p.available_stock', 'p.available',
            'IF(p.parent_id IS NULL, p.restock_time, IF(p.restock_time IS NULL, parent.restock_time, p.restock_time)) AS restock_time',
            'IF(p.parent_id IS NULL, p.is_closeout, IF(p.is_closeout IS NULL, parent.is_closeout, p.is_closeout)) AS is_closeout',
            'p.purchase_steps', 'p.max_purchase', 'p.min_purchase', 'p.purchase_unit', 'p.reference_unit',
            'IF(p.parent_id IS NULL, p.shipping_free, IF(p.shipping_free IS NULL, parent.shipping_free, p.shipping_free)) AS shipping_free',
            'IF(p.parent_id IS NULL, p.purchase_price, IF(p.purchase_price IS NULL, parent.purchase_price, p.purchase_price)) AS purchase_price',
            'IF(p.parent_id IS NULL, p.mark_as_topseller, IF(p.mark_as_topseller IS NULL, parent.mark_as_topseller, IF(p.available = 1, p.mark_as_topseller, parent.mark_as_topseller))) AS mark_as_topseller',
            'p.weight', 'p.height', 'p.length',
            'IF(p.parent_id IS NULL, p.release_date, IF(p.release_date IS NULL, parent.release_date, p.release_date)) AS release_date',
            'p.whitelist_ids', 'p.blacklist_ids', 'p.configurator_group_config', 'p.created_at', 'p.updated_at', 'parent.updated_at AS parent_updated_at',
            'IF(p.parent_id IS NULL, p.rating_average, parent.rating_average) AS rating_average', 'p.display_group', 'p.child_count',
            'currency.iso_code AS currency', 'currency.factor AS currency_factor',
            'tax.tax_rate', 'delivery_time_translation.name AS delivery_time_name',
            'unit_translation.name AS unit_name', 'unit_translation.short_code AS unit_short_code',
            'IF(p.parent_id IS NULL, LOWER(HEX(p.id)), LOWER(HEX(p.parent_id))) AS group_id'
        ];
    }

    /**
     * Product purchasable logic depending on the default filter
     *
     * @param $row
     * @return int
     */
    public function getProductPurchasableValue($row) : int
    {
        if($row['is_closeout'] == 1 && $row['stock'] == 0)
        {
            return 0;
        }

        return 1;
    }

    /**
     * Product immediate delivery logic as per default facet handler logic
     *
     * @see Shopware\Bundle\SearchBundleDBAL\FacetHandler\ImmediateDeliveryFacetHandler
     * @param $row
     * @return int
     */
    public function getProductImmediateDeliveryValue($row) : int
    {
        if($row['available_stock'] >= $row['min_purchase'])
        {
            return 1;
        }

        return 0;
    }

    /**
     * Group product value per solr logic
     *
     * @param $row
     * @return mixed
     */
    public function getProductGroupValue($row)
    {
        if(is_null($row['parent_id']))
        {
            return $row['id'];
        }

        return $row['parent_id'];
    }

    /**
     * Fields required for DI structure
     * (by default, Shopware6 has the "Mark products as 'new', for ? days" which can be used for the logic
     *
     * @return bool
     */
    public function getIsNew() : string
    {
        return (string) false;
    }

    /**
     * Fields required for DI structure
     *
     * @return string
     */
    public function getInSales() : string
    {
        return (string) false;
    }

    /**
     * Extension to the product export
     * Can be used in the XML DI declaration in order to add new elements to the exported component
     * (if it is not a single table)
     *
     * @param ItemsAbstract $extraExporter
     * @return $this
     */
    public function addItemExporter(ItemsAbstract $extraExporter)
    {
        $this->itemExportersList->append($extraExporter);
        return $this;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @return QueryBuilder
     */
    public function addDeltaStateRecognition(QueryBuilder $queryBuilder) : QueryBuilder
    {
        return $this->deltaStateRecognitionHandler->setAccount($this->getAccount())->addState($queryBuilder);
    }

    /**
     * @return array
     */
    public function getIds() : array
    {
        return array_filter(array_unique($this->exportedProductIds));
    }

}
