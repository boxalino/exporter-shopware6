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

    /**
     * @param array $ids
     * @return ExporterInterface
     */
    public function setIds(array $ids) : ExporterInterface;

}
