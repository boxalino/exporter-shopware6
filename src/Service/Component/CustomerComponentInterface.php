<?php
namespace Boxalino\Exporter\Service\Component;

/**
 * Interface OrderComponentInterface
 *
 * @package Boxalino\Exporter\Service\Component
 */
interface CustomerComponentInterface extends ExporterComponentInterface
{
    CONST EXPORTER_LIMIT = 10000000;
    CONST EXPORTER_STEP = 10000;
    CONST EXPORTER_DATA_SAVE_STEP = 1000;
    CONST EXPORTER_COMPONENT_ID_FIELD = "id";
    CONST EXPORTER_COMPONENT_MAIN_FILE = "customers.csv";
    CONST EXPORTER_COMPONENT_TYPE = "customers";
}
