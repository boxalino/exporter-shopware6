<?php
namespace Boxalino\Exporter\Service\Item;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Rule\Rule;
use Boxalino\Exporter\Service\Component\Product;
use Boxalino\Exporter\Service\Util\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;

/**
 * Class PriceAdvanced
 *
 * @package Boxalino\Exporter\Service\Item
 */
class PriceAdvanced extends ItemsAbstract
{

    CONST EXPORTER_COMPONENT_ITEM_NAME = "advanced_price";

    /**
     * @var SalesChannelContextService
     */
    protected $salesChannelContextService;

    /**
     * @var \ArrayObject
     */
    protected $rules;

    /**
     * @var Rule
     */
    protected $rule = null;

    public function __construct(
        Connection $connection,
        LoggerInterface $boxalinoLogger,
        Configuration $exporterConfigurator,
        SalesChannelContextServiceInterface $salesChannelContextService
    ){
        $this->rules = new \ArrayObject();
        $this->salesChannelContextService = $salesChannelContextService;
        parent::__construct($connection, $boxalinoLogger, $exporterConfigurator);
    }

    public function export()
    {
        $this->logger->info("BoxalinoExporter: Preparing products - START ADVANCED PRICE EXPORT FOR "
            . $this->rules->count() . " rules");
        foreach($this->rules as $rule)
        {
            $this->rule = $rule;
            $this->logger->info("BoxalinoExporter: ADVANCED PRICE EXPORT on rule " . $this->rule->getName());
            $this->exportItemRelation();
        }

        $this->logger->info("BoxalinoExporter: Preparing products - END ADVANCED PRICE.");
    }

    public function getItemRelationHeaderColumns(array $additionalFields = []): array
    {
        return [["product_id", "advanced_price"]];
    }

    public function setFilesDefinitions()
    {
        $attributeSourceKey = $this->getLibrary()->addCSVItemFile(
            $this->getFiles()->getPath($this->getItemRelationFile()), 'product_id');
        $this->getLibrary()->addSourceNumberField($attributeSourceKey, $this->getPropertyName(), 'advanced_price');
        $this->getLibrary()->addFieldParameter(
            $attributeSourceKey, $this->getPropertyName(), 'multiValued', 'false');
    }

    public function getItemRelationQuery(int $page = 1): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($this->getRequiredFields())
            ->from("product_price")
            ->leftJoin("product_price", 'rule_condition', 'rule_condition',
                'product_price.rule_id = rule_condition.rule_id AND rule_condition.type = :priceRuleType')
            ->andWhere('product_price.quantity_start = 1')
            ->andWhere('product_price.product_version_id = :live')
            ->andWhere('product_price.version_id = :live')
            ->addGroupBy('product_price.product_id')
            ->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY)
            ->setParameter('priceRuleType', $this->rule->getName(), ParameterType::STRING)
            ->setFirstResult(($page - 1) * Product::EXPORTER_STEP)
            ->setMaxResults(Product::EXPORTER_STEP);

        return $query;
    }

    /**
     * @return string
     */
    public function getPropertyName() : string
    {
        return self::EXPORTER_COMPONENT_ITEM_NAME . "_" .$this->rule->getName();
    }

    /**
     * @return string
     */
    public function getItemMainFile() : string
    {
        return $this->getPropertyName() . ".csv";
    }

    /**
     * @return string
     */
    public function getItemRelationFile() : string
    {
        return "products_". $this->getPropertyName() . ".csv";
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

        if ($salesChannelContext->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            $this->logger->info("BoxalinoExporter: ADVANCED PRICE EXPORT TYPE: " . CartPrice::TAX_STATE_GROSS);
            return [
                'FORMAT(JSON_EXTRACT(JSON_EXTRACT(product_price.price, \'$.*.gross\'),\'$[0]\'), 2) AS advanced_price',
                'LOWER(HEX(product_price.product_id)) AS product_id'
            ];
        }

        $this->logger->info("BoxalinoExporter: ADVANCED PRICE EXPORT TYPE: " . CartPrice::TAX_STATE_NET);
        return [
            'FORMAT(JSON_EXTRACT(JSON_EXTRACT(product_price.price, \'$.*.net\'),\'$[0]\'), 2) AS advanced_price',
            'LOWER(HEX(product_price.product_id)) AS product_id'
        ];
    }

    /**
     * Adds advanced price rules to be exported via XML
     *
     * @param Rule $service
     * @return $this
     */
    public function addAdvancedPriceRuleService(Rule $service) : self
    {
        $this->rules->append($service);
        return $this;
    }

}
