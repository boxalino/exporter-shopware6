<?php
namespace Boxalino\Exporter\Service\Component;

use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class Customer
 * Customer component exporting logic
 *
 * @package Boxalino\Exporter\Service\Component
 */
class Customer extends ExporterComponentAbstract
    implements CustomerComponentInterface
{

    CONST EXPORTER_LIMIT = 10000000;
    CONST EXPORTER_STEP = 10000;
    CONST EXPORTER_DATA_SAVE_STEP = 1000;

    CONST EXPORTER_COMPONENT_ID_FIELD = "id";
    CONST EXPORTER_COMPONENT_MAIN_FILE = "customers.csv";
    CONST EXPORTER_COMPONENT_TYPE = "customers";

    public function export()
    {
        if (!$this->config->isCustomersExportEnabled($this->getAccount()))
        {
            $this->logger->info("BoxalinoExporter: Customers export is disabled.");
            return true;
        }

        parent::export();
    }

    /**
     * Customers export
     */
    public function exportComponent()
    {
        $properties = $this->getRequiredProperties();
        $defaultLanguageId = $this->config->getChannelDefaultLanguageId($this->getAccount());
        $latestOrderSQL = $this->getLastAddressSql();

        $header = true;  $totalCount = 0;  $page = 1;
        while (self::EXPORTER_LIMIT > $totalCount + self::EXPORTER_STEP)
        {
            $data = [];
            $this->logger->info("BoxalinoExporter: Customers export - OFFSET " . $totalCount);
            $query = $this->connection->createQueryBuilder();
            $query->select($properties)
                ->from('customer')
                ->leftJoin(
                    'customer',
                    "( " . $latestOrderSQL->__toString() . ") ",
                    'customer_address',
                    'customer_address.customer_id = customer.id'
                )
                ->leftJoin(
                    'customer_address',
                    'country',
                    'country',
                    'customer_address.country_id = country.id'
                )
                ->leftJoin(
                    'customer_address',
                    'country_state_translation',
                    'cst',
                    'customer_address.country_state_id = cst.country_state_id AND customer.language_id = cst.language_id'
                )
                ->leftJoin(
                    'customer',
                    'sales_channel_translation',
                    'sales_channel_translation',
                    'customer.sales_channel_id = sales_channel_translation.sales_channel_id'
                )
                ->leftJoin(
                    'customer',
                    'payment_method_translation',
                    'payment_method_translation',
                    'customer.default_payment_method_id = payment_method_translation.payment_method_id'
                )
                ->leftJoin(
                    'customer',
                    'payment_method_translation',
                    'last_payment_method_translation',
                    'customer.last_payment_method_id = last_payment_method_translation.payment_method_id'
                )
                ->leftJoin(
                    'customer',
                    'salutation_translation',
                    'salutation_translation',
                    'customer.salutation_id = salutation_translation.salutation_id AND customer.language_id = salutation_translation.language_id'
                )
                ->leftJoin(
                    'customer',
                    'language',
                    'language',
                    'customer.language_id = language.id'
                )
                ->leftJoin(
                    'customer',
                    'customer_group_translation',
                    'customer_group_translation',
                    'customer.customer_group_id=customer_group_translation.customer_group_id AND customer_group_translation.language_id=:defaultLanguageId'
                )
                ->leftJoin(
                    'language',
                    'locale',
                    'locale',
                    'locale.id = language.locale_id'
                )
                ->andWhere("customer.sales_channel_id=:channelId")
                ->groupBy('customer.id')
                ->setParameter("live",  Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY)
                ->setParameter('channelId', Uuid::fromHexToBytes($this->config->getAccountChannelId($this->getAccount())), ParameterType::BINARY)
                ->setParameter("defaultLanguageId", Uuid::fromHexToBytes($defaultLanguageId), ParameterType::BINARY)
                ->setFirstResult(($page - 1) * self::EXPORTER_STEP)
                ->setMaxResults(self::EXPORTER_STEP);

            $count = $query->execute()->rowCount();
            $totalCount+=$count;
            if($totalCount == 0)
            {
                break;
            }
            $results = $this->processExport($query);
            foreach($results as $row)
            {
                if($header)
                {
                    $exportFields = array_keys($row); $this->setHeaderFields($exportFields); $data[] = $exportFields; $header = false;
                }
                $data[] = $row;
                if(count($data) > self::EXPORTER_DATA_SAVE_STEP)
                {
                    $this->getFiles()->savePartToCsv($this->getComponentMainFile(), $data);
                    $data = [];
                }
            }

            $this->getFiles()->savePartToCsv($this->getComponentMainFile(), $data);
            $this->logger->info("BoxalinoExporter: Customers export - Current page: {$page}, data count: {$totalCount}");
            $data=[]; $page++;
            if($count < self::EXPORTER_STEP - 1)
            {
                $this->setSuccessOnComponentExport(true);
                break;
            }
        }

        if($this->getSuccessOnComponentExport())
        {
            $customerSourceKey = $this->getLibrary()->addMainCSVCustomerFile($this->getFiles()->getPath($this->getComponentMainFile()), $this->getComponentIdField());
            foreach ($this->getHeaderFields() as $attribute)
            {
                if ($attribute == $this->getComponentIdField()) continue;
                $this->getLibrary()->addSourceStringField($customerSourceKey, $attribute, $attribute);
            }
        }
    }

    /**
     * @return QueryBuilder
     */
    protected function getLastAddressSql() : QueryBuilder
    {
        $latestAddressSQL = $this->connection->createQueryBuilder()
            ->select(['MAX(order_id) as max_id', 'order_version_id AS latest_address_order_version_id', 'customer_id'])
            ->from("order_customer")
            ->groupBy("customer_id");

        return $this->connection->createQueryBuilder()
            ->select(["*"])
            ->from("(" . $latestAddressSQL->__toString() .")", "latest_order" )
            ->leftJoin("latest_order", "order_address", 'oc',
                'oc.order_id=latest_order.max_id AND oc.order_version_id=latest_order.latest_address_order_version_id')
            ->andWhere("latest_order.latest_address_order_version_id = :live");
    }

    /**
     * Getting the customer attributes list
     * @deprecated
     * @return array
     * @throws \Exception
     */
    public function getFields() : array
    {
        $this->logger->info('BoxalinoExporter: Customers export - get all attributes for account: ' . $this->getAccount());

        $attributesList = [];
        $attributes = $this->getPropertiesByTableList(['customer', 'customer_address']);
        $excludeFieldsFromMain = ['id','salutation_id', 'title', $this->getComponentIdField(), 'customer_id', 'country_id', 'country_state_id', 'custom_fields'];
        foreach ($attributes as $attribute)
        {
            if (in_array($attribute['COLUMN_NAME'], $this->getExcludedProperties())) {
                continue;
            }
            if (in_array($attribute['COLUMN_NAME'], $excludeFieldsFromMain) && $attribute['TABLE_NAME'] != 'customer') {
                continue;
            }
            $attributesList["{$attribute['TABLE_NAME']}.{$attribute['COLUMN_NAME']}"] = $attribute['COLUMN_NAME'];
        }

        return $attributesList;
    }

    /**
     * @return array
     */
    public function getRequiredProperties() : array
    {
        return [
            "LOWER(HEX(customer.id)) as id", "customer.auto_increment", "LOWER(HEX(customer.customer_group_id)) AS customer_group_id",
            "LOWER(HEX(customer.default_payment_method_id)) AS default_payment_method_id","LOWER(HEX(customer.language_id)) AS language_id",
            "LOWER(HEX(customer.last_payment_method_id)) AS last_payment_method_id","customer.customer_number",
            "customer.first_name","customer.last_name","customer.company","customer.email","customer.title","customer.active",
            "customer.guest","customer.first_login","customer.last_login","customer.newsletter","customer.birthday",
            "customer.last_order_date","customer.order_count","customer.custom_fields","customer.affiliate_code",
            "customer.campaign_code","customer.created_at","customer.updated_at","customer.remote_address",
            'locale.code as languagecode', 'country.iso as countryiso', 'cst.name as statename',
            'sales_channel_translation.name as shopname', 'payment_method_translation.name as preferred_payment_method',
            'salutation_translation.display_name as salutation', 'last_payment_method_translation.name as last_payment_method',
            "customer_group_translation.name AS customer_group_name",
            "customer_address.company as address_company","customer_address.department","customer_address.street",
            "customer_address.zipcode","customer_address.city","customer_address.vat_id","customer_address.phone_number",
            "customer_address.additional_address_line1","customer_address.additional_address_line2","customer_address.custom_fields as address_custom_fields",
            "customer_address.created_at as address_created_at","customer_address.updated_at as address_updated_at"
        ];
    }

    /**
     * @return array
     */
    public function getExcludedProperties() : array
    {
        return [
            'password',
            'legacy_password',
            'legacy_encoder',
            'hash'
        ];
    }

}
