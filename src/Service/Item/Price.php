<?php
namespace Boxalino\Exporter\Service\Item;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Boxalino\Exporter\Service\Component\Product;
use Boxalino\Exporter\Service\Util\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;

/**
 * Class Price
 * Exports price and list price (if set) for the products
 *
 * @package Boxalino\Exporter\Service\Item
 */
class Price extends ItemsAbstract
{

    CONST EXPORTER_COMPONENT_ITEM_NAME = "price";
    CONST EXPORTER_COMPONENT_ITEM_MAIN_FILE = 'prices.csv';
    CONST EXPORTER_COMPONENT_ITEM_RELATION_FILE = 'product_price.csv';

    /**
     * @var SalesChannelContextService
     */
    protected $salesChannelContextService;

    public function __construct(
        Connection $connection,
        LoggerInterface $boxalinoLogger,
        Configuration $exporterConfigurator,
        SalesChannelContextServiceInterface $salesChannelContextService
    ){
        $this->salesChannelContextService = $salesChannelContextService;
        parent::__construct($connection, $boxalinoLogger, $exporterConfigurator);
    }

    public function export()
    {
        $this->logger->info("BoxalinoExporter: Preparing products - START PRICE EXPORT.");
        $this->exportItemRelation();
        $this->logger->info("BoxalinoExporter: Preparing products - END PRICE.");
    }

    public function getItemRelationHeaderColumns(array $additionalFields = []): array
    {
        return [["product_id", "price", "list_price", "price_net", "price_gross", "list_price_net", "list_price_gross"]];
    }

    /**
     * @throws \Exception
     */
    public function setFilesDefinitions()
    {
        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath($this->getItemRelationFile()), 'product_id');
        $this->getLibrary()->addSourceDiscountedPriceField($attributeSourceKey, 'price');
        $this->getLibrary()->addSourceListPriceField($attributeSourceKey, 'list_price');

        $this->getLibrary()->addFieldParameter($attributeSourceKey,'bx_listprice', 'pc_fields', 'CASE WHEN (price.list_price IS NULL OR price.list_price <= 0) AND price.price IS NOT NULL then price.price ELSE price.list_price END as price_value');
        $this->getLibrary()->addFieldParameter($attributeSourceKey,'bx_listprice', 'pc_tables', 'LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_product_price` as price ON t.product_id = price.product_id');

        $this->getLibrary()->addSourceNumberField($attributeSourceKey, 'bx_grouped_price', 'price');
        $this->getLibrary()->addFieldParameter($attributeSourceKey, 'bx_grouped_price', 'multiValued', 'false');
        $this->getLibrary()->addSourceNumberField($attributeSourceKey, 'price_net', 'price_net');
        $this->getLibrary()->addFieldParameter($attributeSourceKey, 'price_net', 'multiValued', 'false');
        $this->getLibrary()->addSourceNumberField($attributeSourceKey, 'price_gross', 'price_gross');
        $this->getLibrary()->addFieldParameter($attributeSourceKey, 'price_gross', 'multiValued', 'false');

        $this->getLibrary()->addSourceNumberField($attributeSourceKey, 'list_price_net', 'list_price_net');
        $this->getLibrary()->addFieldParameter($attributeSourceKey, 'list_price_net', 'multiValued', 'false');
        $this->getLibrary()->addFieldParameter($attributeSourceKey,'list_price_net', 'pc_fields', 'CASE WHEN (price.list_price_net IS NULL OR price.list_price_net <= 0) AND price.price_net IS NOT NULL then price.price_net ELSE price.list_price_net END as price_value');
        $this->getLibrary()->addFieldParameter($attributeSourceKey,'list_price_net', 'pc_tables', 'LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_product_price` as price ON t.product_id = price.product_id');

        $this->getLibrary()->addSourceNumberField($attributeSourceKey, 'list_price_gross', 'list_price_gross');
        $this->getLibrary()->addFieldParameter($attributeSourceKey, 'list_price_gross', 'multiValued', 'false');
        $this->getLibrary()->addFieldParameter($attributeSourceKey,'list_price_gross', 'pc_fields', 'CASE WHEN (price.list_price_gross IS NULL OR price.list_price_gross <= 0) AND price.price_gross IS NOT NULL then price.price_gross ELSE price.list_price_gross END as price_value');
        $this->getLibrary()->addFieldParameter($attributeSourceKey,'list_price_gross', 'pc_tables', 'LEFT JOIN `%%EXTRACT_PROCESS_TABLE_BASE%%_products_product_price` as price ON t.product_id = price.product_id');
    }

    public function getItemRelationQuery(int $page = 1): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($this->getRequiredFields())
            ->from("product")
            ->leftJoin("product", 'product', 'parent',
                'product.parent_id = parent.id AND product.parent_version_id = parent.version_id')
            ->andWhere('product.version_id = :live')
            ->addGroupBy('product.id')
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
     * Depending on the channel configuration, the gross or net price is the one displayed to the user
     * @duplicate logic from the src/Core/Content/Product/SalesChannel/Price/ProductPriceDefinitionBuilder.php :: getPriceForTaxState()
     *
     * @return array
     * @throws \Exception
     */
    public function getRequiredFields(): array
    {
        $salesChannelContext = $this->salesChannelContextService->get(
            $this->getChannelId(),
            "boxalinoexporttoken",
            $this->getChannelDefaultLanguage()
        );

        $priceFields = [
            'IF(product.price IS NULL, FORMAT(JSON_EXTRACT(JSON_EXTRACT(parent.price, \'$.*.gross\'),\'$[0]\'), 2), FORMAT(JSON_EXTRACT(JSON_EXTRACT(product.price, \'$.*.gross\'),\'$[0]\'), 2)) AS price_gross',
            'IF(product.price IS NULL, FORMAT(JSON_EXTRACT(JSON_EXTRACT(parent.price, \'$.*.net\'),\'$[0]\'), 2), FORMAT(JSON_EXTRACT(JSON_EXTRACT(product.price, \'$.*.net\'),\'$[0]\'), 2)) AS price_net',
            'IF(product.price IS NULL, FORMAT(JSON_EXTRACT(JSON_EXTRACT(parent.price, \'$.*.listPrice\'),\'$[0].net\'), 2), FORMAT(JSON_EXTRACT(JSON_EXTRACT(product.price, \'$.*.listPrice\'),\'$[0].net\'), 2)) AS list_price_net',
            'IF(product.price IS NULL, FORMAT(JSON_EXTRACT(JSON_EXTRACT(parent.price, \'$.*.listPrice\'),\'$[0].gross\'), 2),FORMAT(JSON_EXTRACT(JSON_EXTRACT(product.price, \'$.*.listPrice\'),\'$[0].gross\'), 2)) AS list_price_gross',
            'LOWER(HEX(product.id)) AS product_id',
        ];

        if ($salesChannelContext->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $this->logger->info("BoxalinoExporter: PRICE EXPORT TYPE: " . CartPrice::TAX_STATE_GROSS);
            return array_merge($priceFields, [
                'IF(product.price IS NULL, FORMAT(JSON_EXTRACT(JSON_EXTRACT(parent.price, \'$.*.gross\'),\'$[0]\'), 2), FORMAT(JSON_EXTRACT(JSON_EXTRACT(product.price, \'$.*.gross\'),\'$[0]\'), 2)) AS price',
                'IF(product.price IS NULL, FORMAT(JSON_EXTRACT(JSON_EXTRACT(parent.price, \'$.*.listPrice\'),\'$[0].net\'), 2), FORMAT(JSON_EXTRACT(JSON_EXTRACT(product.price, \'$.*.listPrice\'),\'$[0].gross\'), 2)) AS list_price'
            ]);
        }

        $this->logger->info("BoxalinoExporter: PRICE EXPORT TYPE: " . CartPrice::TAX_STATE_NET);
        return array_merge($priceFields, [
            'IF(product.price IS NULL, FORMAT(JSON_EXTRACT(JSON_EXTRACT(parent.price, \'$.*.net\'),\'$[0]\'), 2), FORMAT(JSON_EXTRACT(JSON_EXTRACT(product.price, \'$.*.net\'),\'$[0]\'), 2)) AS price',
            'IF(product.price IS NULL, FORMAT(JSON_EXTRACT(JSON_EXTRACT(parent.price, \'$.*.listPrice\'),\'$[0].net\'), 2), FORMAT(JSON_EXTRACT(JSON_EXTRACT(product.price, \'$.*.listPrice\'),\'$[0].net\'), 2)) AS list_price'
        ]);
    }

}
