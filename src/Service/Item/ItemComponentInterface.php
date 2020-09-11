<?php
namespace Boxalino\Exporter\Service\Item;

use Boxalino\Exporter\Service\ExporterInterface;

/**
 * Interface ItemComponentInterface
 *
 * @package Boxalino\Exporter\Service
 */
interface ItemComponentInterface extends ExporterInterface
{
    /**
     * @return array
     */
    public function getRequiredFields() : array;

    /**
     * @return string
     */
    public function getItemMainFile() : string;

    /**
     * @return string
     */
    public function getPropertyName() : string;
}
