<?php
namespace Boxalino\Exporter\Service\Util;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Psr\Log\LoggerInterface;

/**
 * Class Configuration
 * Exporter configuration helper
 * Contains all the configuration data required for the exporter to be managed
 *
 * @package Boxalino\Exporter\Service\Util
 */
class Configuration extends \Boxalino\RealTimeUserExperience\Service\Util\Configuration
{
    CONST BOXALINO_CONFIG_KEY = "BoxalinoExporter";

    /**
     * @var array
     */
    protected $exporterConfigurationFields = [
        "account",
        "password",
        "devIndex",
        "export",
        "exportPublishConfig",
        "exportProductImages",
        "exportProductUrl",
        "exportCustomerEnable",
        "exportTransactionEnable",
        "exportTransactionMode",
        "exportVoucherEnable",
        "exportCronSchedule",
        "productsExtraTable",
        "customersExtraTable",
        "transactionsExtraTable",
        "exportDeltaFrequency",
        "exportTimeout",
        "temporaryExportPath"
    ];

    /**
     * @var array
     */
    protected $indexConfig = [];

    /**
     * @param SystemConfigService $systemConfigService
     * @param \Psr\Log\LoggerInterface $boxalinoLogger
     * @param Connection $connection
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    public function __construct(
        SystemConfigService $systemConfigService,
        Connection $connection,
        LoggerInterface $boxalinoLogger
    ) {
        parent::__construct($systemConfigService, $connection, $boxalinoLogger);
        $this->init();
    }

    /**
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    protected function init()
    {
        foreach($this->getShops() as $shopData)
        {
            $pluginConfig = $this->getPluginConfigByChannelId($shopData['sales_channel_id']);
            if(!$pluginConfig['export'])
            {
                $this->logger->info("BoxalinoExporter:: Exporter disabled on channel $channel; Plugin Configurations skipped.");
                continue;
            }

            $config = $this->validateChannelConfig($pluginConfig, $shopData['sales_channel_name']);
            if(empty($config)) { continue; }
            if(!isset($this->indexConfig[$config['account']]))
            {
                $this->indexConfig[$config['account']] = array_merge($shopData, $config);
            }
        }
    }

    /**
     * @param $config
     * @param $channel
     * @return array
     */
    public function validateChannelConfig($config, $channel)
    {
        if (empty($config['account']) || empty($config['password']))
        {
            $this->logger->info("BoxalinoExporter:: Account or exporter password not found on channel $channel; Plugin Configurations skipped.");
            return [];
        }

        foreach($this->exporterConfigurationFields as $field)
        {
            if(!isset($config[$field])) {$config[$field] = "";}
        }

        return $config;
    }

    /**
     * Getting shop details: id, languages, root category
     *
     * @return array
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    protected function getShops() : array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(sales_channel.id)) as sales_channel_id',
            'LOWER(HEX(sales_channel.language_id)) AS sales_channel_default_language_id',
            'LOWER(HEX(sales_channel.currency_id)) AS sales_channel_default_currency_id',
            'LOWER(HEX(sales_channel.customer_group_id)) as sales_channel_customer_group_id',
            'MIN(channel.name) as sales_channel_name',
            "GROUP_CONCAT(SUBSTR(locale.code, 1, 2) SEPARATOR ',') as sales_channel_languages_locale",
            "GROUP_CONCAT(LOWER(HEX(language.id)) SEPARATOR ',') as sales_channel_languages_id",
            'LOWER(HEX(sales_channel.navigation_category_id)) as sales_channel_navigation_category_id',
            'LOWER(HEX(sales_channel.navigation_category_version_id)) as sales_channel_navigation_category_version_id'
        ])
            ->from('sales_channel')
            ->leftJoin(
                'sales_channel',
                'sales_channel_language',
                'sales_channel_language',
                'sales_channel.id = sales_channel_language.sales_channel_id'
            )
            ->leftJoin(
                'sales_channel',
                'sales_channel_translation',
                'channel',
                'sales_channel.id = channel.sales_channel_id'
            )
            ->leftJoin(
                'sales_channel_language',
                'language',
                'language',
                'sales_channel_language.language_id = language.id'
            )
            ->leftJoin(
                'language',
                'locale',
                'locale',
                'language.locale_id = locale.id'
            )
            ->addGroupBy('sales_channel.id')
            ->andWhere('sales_channel.active = 1')
            ->andWhere('sales_channel.type_id = :type')
            ->setParameter('type', Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT), ParameterType::BINARY);

        return $query->execute()->fetchAll();
    }

    /**
     * @param string $account
     * @throws \Exception
     */
    public function getChannelDefaultLanguageId(string $account) : string
    {
        $config = $this->getAccountConfig($account);
        return $config['sales_channel_default_language_id'];
    }

    /**
     * @return array
     */
    public function getAccounts() : array
    {
        return array_keys($this->indexConfig);
    }

    /**
     * @param $account
     * @return mixed
     * @throws \Exception
     */
    public function getAccountConfig(string $account) : array
    {
        if(isset($this->indexConfig[$account]))
        {
            return $this->indexConfig[$account];
        }

        throw new \Exception("Account is not defined: " . $account);
    }

    /**
     * @param $account
     * @return mixed
     * @throws \Exception
     */
    public function getCustomerGroupId(string $account)  : string
    {
        $config = $this->getAccountConfig($account);
        return $config['sales_channel_customer_group_id'];
    }

    /**
     * @param $account
     * @return mixed
     * @throws \Exception
     */
    public function getChannelRootCategoryId(string $account) : string
    {
        $config = $this->getAccountConfig($account);
        return $config['sales_channel_navigation_category_id'];
    }

    /**
     * @param $account
     * @return bool
     * @throws \Exception
     */
    public function isCustomersExportEnabled(string $account) : bool
    {
        $config = $this->getAccountConfig($account);
        return (bool)$config['exportCustomerEnable'];
    }

    /**
     * @param $account
     * @return bool
     * @throws \Exception
     */
    public function isTransactionsExportEnabled(string $account) : bool
    {
        $config = $this->getAccountConfig($account);
        return (bool) $config['exportTransactionEnable'];
    }

    /**
     * @param $account
     * @return bool
     * @throws \Exception
     */
    public function isVoucherExportEnabled(string $account) : bool
    {
        $config = $this->getAccountConfig($account);
        return (bool) $config['exportVoucherEnable'];
    }

    /**
     * @param $account
     * @return string
     * @throws \Exception
     */
    public function getExportTransactionIncremental(string $account) : string
    {
        $config = $this->getAccountConfig($account);
        return (bool) $config['exportTransactionMode'];
    }

    /**
     * Getting additional tables for each entity to be exported (products, customers, transactions)
     *
     * @param string $account
     * @param string $type
     * @return array
     * @throws \Exception
     */
    public function getAccountExtraTablesByComponent(string $account, string $type) : array
    {
        $config = $this->getAccountConfig($account);
        $additionalTablesList = $config["{$type}ExtraTable"];
        if($additionalTablesList)
        {
            return explode(',', $additionalTablesList);
        }

        return [];
    }

    /**
     * @param $account
     * @return mixed
     * @throws \Exception
     */
    public function getAccountPassword(string $account) : string
    {
        $config = $this->getAccountConfig($account);
        $password = $config['password'];
        if(empty($password) || is_null($password)) {
            throw new \Exception("Please provide a password for your boxalino account in the configuration");
        }

        return $password;
    }

    /**
     * @param $account
     * @return mixed
     * @throws \Exception
     */
    public function useDevIndex(string $account) : bool
    {
        $config = $this->getAccountConfig($account);
        try{
            return (bool)$config['devIndex'];
        } catch (\Exception $exception)
        {
            return false;
        }
    }

    /**
     * @param $account
     * @return mixed
     * @throws \Exception
     */
    public function getAccountChannelId(string $account) : string
    {
        $config = $this->getAccountConfig($account);
        return $config['sales_channel_id'];
    }

    /**
     * @param $account
     * @return []
     * @throws \Exception
     */
    public function getAccountLanguages(string $account) : array
    {
        $config = $this->getAccountConfig($account);
        $languages = explode(",", $config['sales_channel_languages_locale']);
        $languageIds = explode(",", $config['sales_channel_languages_id']);
        return array_combine($languageIds, $languages);
    }

    /**
     * @param string $account
     * @return null | string
     */
    public function getExportTemporaryArchivePath(string $account) : ?string
    {
        $config = $this->getAccountConfig($account);
        return empty($config["temporaryExportPath"]) ? null : $config["temporaryExportPath"];
    }

    /**
     * @param $account
     * @return bool
     * @throws \Exception
     */
    public function exportProductImages(string $account) : bool
    {
        $config = $this->getAccountConfig($account);
        return (bool) $config['exportProductImages'];
    }

    /**
     * @param $account
     * @return bool
     * @throws \Exception
     */
    public function exportProductUrl(string $account) : bool
    {
        $config = $this->getAccountConfig($account);
        return (bool)$config['exportProductUrl'];
    }

    /**
     * @param $account
     * @return bool
     * @throws \Exception
     */
    public function publishConfigurationChanges(string $account) : bool
    {
        $config = $this->getAccountConfig($account);
        return (bool) $config['exportPublishConfig'];
    }

    /**
     * @param string $account
     * @return int
     * @throws \Exception
     */
    public function getExporterTimeout(string $account) : int
    {
        $config = $this->getAccountConfig($account);
        if(isset($config['exportTimeout']) && !empty($config['exportTimeout']))
        {
            return $config['exportTimeout'];
        }

        return 300;
    }

    /**
     * Time interval after a full data export that a delta is allowed to run
     * It is set in order to avoid overlapping index updates
     *
     * @param string $account
     * @return int
     * @throws \Exception
     */
    public function getDeltaScheduleTime(string $account) : int
    {
        $config = $this->getAccountConfig($account);
        if(isset($config['exportCronSchedule']) && !empty($config['exportCronSchedule']))
        {
            return $config['exportCronSchedule'];
        }

        return 60;
    }

    /**
     * Minimum time interval between 2 deltas to allow a run (minutes)
     *
     * @param string $account
     * @return int
     * @throws \Exception
     */
    public function getDeltaFrequencyMinInterval(string $account) : int
    {
        $config = $this->getAccountConfig($account);
        if(isset($config['exportDeltaFrequency']) && !empty($config['exportDeltaFrequency']))
        {
            return $config['exportDeltaFrequency'];
        }

        return 30;
    }

}
