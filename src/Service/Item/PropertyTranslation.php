<?php
namespace Boxalino\Exporter\Service\Item;

use Boxalino\Exporter\Service\Component\Product;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class PropertyTranslatiun
 * check src/Core/Content/Property/PropertyGroupDefinition.php for other property types and definitions
 * Exports all product-property relations which assign filterable information such as size or color to the product
 *
 * @package Boxalino\Exporter\Service\Item
 */
abstract class PropertyTranslation extends ItemsAbstract
{

    /**
     * @var string
     */
    protected $propertyId;

    /**
     * Accessing store-view level translation for each facet option
     *
     * @param int $page
     * @return QueryBuilder
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    protected function getLocalizedPropertyQuery(int $page = 1) : QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($this->getRequiredFields())
            ->from("property_group_option")
            ->leftJoin('property_group_option', '( ' . $this->getLocalizedFieldsQuery()->__toString() . ') ',
                'translation', 'translation.property_group_option_id = property_group_option.id')
            ->andWhere($this->getLanguageHeaderConditional())
            ->addGroupBy('property_group_option.id')
            ->setFirstResult(($page - 1) * Product::EXPORTER_STEP)
            ->setMaxResults(Product::EXPORTER_STEP);

        if(!is_null($this->propertyId))
        {
            $query->andWhere('property_group_option.property_group_id = :propertyGroupId')
                ->setParameter('propertyGroupId', Uuid::fromHexToBytes($this->propertyId), ParameterType::BINARY);
        }

        return $query;
    }

    /**
     * @param string $property
     * @return \Doctrine\DBAL\Query\QueryBuilder
     * @throws \Exception
     */
    protected function getLocalizedFieldsQuery() : QueryBuilder
    {
        return $this->getLocalizedFields('property_group_option_translation',
            'property_group_option_id', 'property_group_option_id',
            'property_group_option_id', "name",
            ['property_group_option_translation.property_group_option_id']
        );
    }

    /**
     * All translation fields from the product_group_option* table
     *
     * @return array
     * @throws \Exception
     */
    public function getRequiredFields(): array
    {
        return array_merge($this->getLanguageHeaderColumns(),
            ["LOWER(HEX(property_group_option.id)) AS '{$this->getPropertyIdField()}'"]
        );
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getMainHeaderColumns() : array
    {
        return [array_merge($this->getLanguageHeaders(), [$this->getPropertyIdField()])];
    }

    /**
     * Get existing facets names&codes
     *
     * @return false|mixed
     */
    public function getPropertyNames() : array
    {
        $query = $this->connection->createQueryBuilder()
            ->select(["LOWER(HEX(property_group.id)) AS property_group_id", "IF(pgtl.name IS NULL, pgt.name, pgtl.name) AS name"])
            ->from("property_group")
            ->leftJoin("property_group", "property_group_translation", "pgt","property_group.id = pgt.property_group_id")
            ->leftJoin("property_group", "property_group_translation", "pgtl",
                "property_group.id = pgtl.property_group_id AND pgtl.language_id=:languageId")
            ->groupBy("property_group.id")
            ->setParameter("languageId", Uuid::fromHexToBytes($this->getChannelDefaultLanguage()), ParameterType::STRING);

        return $query->execute()->fetchAll(FetchMode::ASSOCIATIVE);
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setPropertyId(string $id)
    {
        $this->propertyId = $id;
        return $this;
    }

}
