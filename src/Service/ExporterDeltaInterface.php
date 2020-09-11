<?php
namespace Boxalino\Exporter\Service;

/**
 * Interface ExporterDeltaInterface
 *
 * @package Boxalino\Exporter\Service
 */
interface ExporterDeltaInterface extends ExporterInterface
{
    /**
     * Product IDs to be exported as part of a delta index update
     *
     * @return array
     */
    public function getIds(): array;
}
