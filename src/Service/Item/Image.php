<?php
namespace Boxalino\Exporter\Service\Item;

/**
 * Class Image
 * Exports the main product image
 * 
 * @package Boxalino\Exporter\Service\Item
 */
class Image extends MediaAbstract
    implements ItemComponentInterface
{
    CONST EXPORTER_COMPONENT_ITEM_NAME = "image";
    CONST EXPORTER_COMPONENT_ITEM_MAIN_FILE = 'image.csv';
    CONST EXPORTER_COMPONENT_ITEM_RELATION_FILE = 'product_image.csv';

    /**
     * @param array $row
     * @return array
     */
    public function processMediaRow(array $row) : array
    {
        $image = $this->getImageByMediaId($row[$this->getPropertyIdField()]);
        $row[$this->getPropertyIdField()] = $image;
        
        return $row;
    }

    public function setFilesDefinitions()
    {
        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath($this->getItemRelationFile()), 'product_id');
        $this->getLibrary()->addSourceStringField($attributeSourceKey, $this->getPropertyName(), $this->getPropertyIdField());
        $this->getLibrary()->addFieldParameter($attributeSourceKey, $this->getPropertyName(), 'multiValued', 'false');
    }
    
    /**
     * @return array
     */
    public function getRequiredFields(): array
    {
        return [
            "LOWER(HEX(product_media.media_id)) AS {$this->getPropertyIdField()}",
            "LOWER(HEX(product.id)) AS product_id"
        ];
    }

}
