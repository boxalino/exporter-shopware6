<?php
namespace Boxalino\Exporter\Service\Util;

use Boxalino\Exporter\Service\ExporterConfigurationInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Trait for accessing Shopware Configuration content
 *
 * @package Boxalino\Exporter\Service\Util
 */
trait ShopwareConfigurationTrait
{

    /**
     * @var SystemConfigService
     */
    protected $systemConfigService;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @param string $id
     * @return array
     */
    public function getPluginConfigByChannelId($id) : array
    {
        if(empty($this->config) || !isset($this->config[$id]))
        {
            $allConfig = $this->systemConfigService->all($id);
            $this->config[$id] = $allConfig[ExporterConfigurationInterface::BOXALINO_CONFIG_KEY]['config'];
        }

        return $this->config[$id];
    }


}
