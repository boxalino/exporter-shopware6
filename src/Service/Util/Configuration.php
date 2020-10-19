<?php
namespace Boxalino\Exporter\Service\Util;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Psr\Log\LoggerInterface;
use Boxalino\Exporter\Service\ExporterConfigurationInterface;

/**
 * Class Configuration
 * Exporter configuration helper
 * Contains all the configuration data required for the exporter to be managed
 * Can be rewritten with the use of the dependency injection (DI)
 *
 * @package Boxalino\Exporter\Service\Util
 */
class Configuration extends \Boxalino\RealTimeUserExperience\Service\Util\Configuration
    implements ExporterConfigurationInterface
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

    protected $account = null;

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
                $this->logger->info("BoxalinoExporter:: Exporter disabled on channel {$shopData['sales_channel_name']}; Plugin Configurations skipped.");
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
     * @throws \Exception
     */
    public function getChannelDefaultLanguageId() : string
    {
        $config = $this->getAccountConfig();
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
     * @return mixed
     * @throws \Exception
     */
    public function getAccountConfig() : array
    {
        if(isset($this->indexConfig[$this->account]))
        {
            return $this->indexConfig[$this->account];
        }
        throw new \Exception("Account is not defined: " . $this->account);
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getCustomerGroupId()  : string
    {
        $config = $this->getAccountConfig();
        return $config['sales_channel_customer_group_id'];
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getChannelRootCategoryId() : string
    {
        $config = $this->getAccountConfig();
        return $config['sales_channel_navigation_category_id'];
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isCustomersExportEnabled() : bool
    {
        $config = $this->getAccountConfig();
        return (bool)$config['exportCustomerEnable'];
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isTransactionsExportEnabled() : bool
    {
        $config = $this->getAccountConfig();
        return (bool) $config['exportTransactionEnable'];
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isVoucherExportEnabled() : bool
    {
        $config = $this->getAccountConfig();
        return (bool) $config['exportVoucherEnable'];
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function isTransactionExportIncremental() : string
    {
        $config = $this->getAccountConfig();
        return (bool) $config['exportTransactionMode'];
    }

    /**
     * Getting additional tables for each entity to be exported (products, customers, transactions)
     *
     * @param string $type
     * @return array
     * @throws \Exception
     */
    public function getExtraTablesByComponent(string $type) : array
    {
        $config = $this->getAccountConfig();
        $additionalTablesList = $config["{$type}ExtraTable"];
        if($additionalTablesList)
        {
            return explode(',', $additionalTablesList);
        }

        return [];
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getPassword() : string
    {
        $config = $this->getAccountConfig();
        $password = $config['password'];
        if(empty($password) || is_null($password)) {
            throw new \Exception("Please provide a password for your boxalino account in the configuration");
        }

        return $password;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function useDevIndex() : bool
    {
        $config = $this->getAccountConfig();
        try{
            return (bool)$config['devIndex'];
        } catch (\Exception $exception)
        {
            return false;
        }
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getChannelId() : string
    {
        $config = $this->getAccountConfig();
        return $config['sales_channel_id'];
    }

    /**
     * @return []
     * @throws \Exception
     */
    public function getLanguages() : array
    {
        $config = $this->getAccountConfig();
        $languages = explode(",", $config['sales_channel_languages_locale']);
        $languageIds = explode(",", $config['sales_channel_languages_id']);
        return array_combine($languageIds, $languages);
    }

    /**
     * @return null | string
     */
    public function getExportTemporaryArchivePath() : ?string
    {
        $config = $this->getAccountConfig();
        return empty($config["temporaryExportPath"]) ? null : $config["temporaryExportPath"];
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function exportProductImages() : bool
    {
        $config = $this->getAccountConfig();
        return (bool) $config['exportProductImages'];
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function exportProductUrl() : bool
    {
        $config = $this->getAccountConfig();
        return (bool)$config['exportProductUrl'];
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function publishConfigurationChanges() : bool
    {
        $config = $this->getAccountConfig();
        return (bool) $config['exportPublishConfig'];
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function getExporterTimeout() : int
    {
        $config = $this->getAccountConfig();
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
     * A full data synchronization can take up to 2h (depending on the size of the export and the complexity of data)
     * For this reason, the daily full data synchronization must happen when there is the least traffic on the store
     *
     * @return int
     * @throws \Exception
     */
    public function getDeltaScheduleTime() : int
    {
        $config = $this->getAccountConfig();
        if(isset($config['exportCronSchedule']) && !empty($config['exportCronSchedule']))
        {
            return $config['exportCronSchedule'];
        }

        return 60;
    }

    /**
     * Minimum time interval between 2 deltas to allow a run (minutes)
     *
     * @return int
     * @throws \Exception
     */
    public function getDeltaFrequencyMinInterval() : int
    {
        $config = $this->getAccountConfig();
        if(isset($config['exportDeltaFrequency']) && !empty($config['exportDeltaFrequency']))
        {
            return $config['exportDeltaFrequency'];
        }

        return 15;
    }

    /**
     * @param string $account
     * @return $this|mixed
     */
    public function setAccount(string $account)
    {
        $this->account = $account;
        return $this;
    }

}
