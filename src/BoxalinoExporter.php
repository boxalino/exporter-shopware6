<?php
namespace Boxalino\Exporter;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

/**
 * Class BoxalinoExporter - data exporter
 *
 * @package Boxalino
 */
class BoxalinoExporter extends Plugin
{

    public function install(InstallContext $installContext): void
    {
    }


    public function activate(ActivateContext $context) : void
    {
    }

    public function update(UpdateContext $updateContext): void
    {
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
    }

}

