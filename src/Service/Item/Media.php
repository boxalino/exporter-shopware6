<?php
namespace Boxalino\Exporter\Service\Item;

/**
 * Class Media
 * @package Boxalino\Exporter\Service\Item
 */
class Media extends MediaAbstract
    implements ItemComponentInterface
{
    CONST EXPORTER_COMPONENT_ITEM_NAME = "media";
    CONST EXPORTER_COMPONENT_ITEM_MAIN_FILE = 'media.csv';
    CONST EXPORTER_COMPONENT_ITEM_RELATION_FILE = 'product_media.csv';

    /**
     * @param array $row
     * @return array
     */
    public function processMediaRow(array $row) : array
    {
        $images = explode('|', $row[$this->getPropertyIdField()]);
        foreach ($images as $index => $image)
        {
            $images[$index] = $this->getImageByMediaId($image);
        }
        
        $row[$this->getPropertyIdField()] = implode('|', $images);
        
        return $row;
    }

    public function setFilesDefinitions()
    {
        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath($this->getItemRelationFile()), 'product_id');
        $this->getLibrary()->addSourceStringField($attributeSourceKey, $this->getPropertyName(), $this->getPropertyIdField());
        $this->getLibrary()->addFieldParameter($attributeSourceKey, $this->getPropertyName(), 'splitValues', '|');
    }

    /**
     * @return array
     */
    public function getRequiredFields(): array
    {
        return [
            "GROUP_CONCAT(LOWER(HEX(product_media.media_id)) ORDER BY product_media.position SEPARATOR '|') AS {$this->getPropertyIdField()}",
            "LOWER(HEX(product.id)) AS product_id"
        ];
    }

}
