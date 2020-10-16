<?php
namespace Boxalino\Exporter\Service\Delta;

use Boxalino\Exporter\Service\StateRecognitionInterface;

/**
 * Interface ProductStateRecognitionInterface
 * 
 * Declares the logic for a delta export (ex: product IDs to be synchronized/updated)
 * Is a required dependency which has to be declared for the repository integration
 * 
 * @package Boxalino\Exporter\Service\Delta
 */
interface ProductStateRecognitionInterface extends StateRecognitionInterface
{
}