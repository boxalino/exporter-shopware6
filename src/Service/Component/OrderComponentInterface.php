<?php
namespace Boxalino\Exporter\Service\Component;

/**
 * Interface OrderComponentInterface
 *
 * @package Boxalino\Exporter\Service\Component
 */
interface OrderComponentInterface extends ExporterComponentInterface
{
    CONST EXPORTER_LIMIT = 10000000;
    CONST EXPORTER_STEP = 5000;
    CONST EXPORTER_DATA_SAVE_STEP = 1000;
    CONST EXPORTER_COMPONENT_ID_FIELD = "order_id";
    CONST EXPORTER_COMPONENT_TYPE = "transactions";
    CONST EXPORTER_COMPONENT_MAIN_FILE = "transactions.csv";
}
