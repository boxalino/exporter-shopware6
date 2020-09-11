<?php
namespace Boxalino\Exporter\Service;

/**
 * Interface ExporterServiceInterface
 *
 * @package Boxalino\Exporter\Service
 */
interface ExporterServiceInterface extends ExporterInterface
{
    /**
     * Exporting products and product elements (tags, manufacturers, category, prices, reviews, etc)
     */
    public function exportProducts() : void;

    /**
     * Export customer data
     */
    public function exportCustomers() : void;

    /**
     * Export order data
     */
    public function exportOrders() : void;

    /**
     * @return bool
     */
    public function getIsDelta() : bool;

    /**
     * @param bool $value
     * @return ExporterServiceInterface
     */
    public function setIsDelta(bool $value) : ExporterServiceInterface;

    /**
     * @param string $account
     * @return ExporterServiceInterface
     */
    public function setAccount(string $account) : ExporterServiceInterface;

    /**
     * @return string
     */
    public function getAccount() : string;

    /**
     * @return string
     */
    public function getType() : string;

    /**
     * @param string $value
     * @return ExporterServiceInterface
     */
    public function setType(string $value) : ExporterServiceInterface;

}
