<?php
namespace Boxalino\Exporter\Service\Item;

use Boxalino\Exporter\Service\Component\ExporterComponentAbstract;
use Boxalino\Exporter\Service\Component\Product;
use Boxalino\Exporter\Service\ExporterInterface;
use Boxalino\Exporter\Service\Util\Configuration;
use Boxalino\Exporter\Service\Util\ContentLibrary;
use Boxalino\Exporter\Service\Util\FileHandler;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use http\Exception\BadQueryStringException;
use http\QueryString;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

abstract class ItemsAbstract implements ExporterInterface
{

    /**
     * @var FileHandler
     */
    protected $files;

    /**
     * @var string
     */
    protected $account;

    /**
     * @var array
     */
    protected $exportedProductIds = [];

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @var ContentLibrary
     */
    protected $library;

    public function __construct(
        Connection $connection,
        LoggerInterface $boxalinoLogger,
        Configuration $exporterConfigurator
    ){
        $this->connection = $connection;
        $this->logger = $boxalinoLogger;
        $this->config = $exporterConfigurator;
    }


    abstract public function export();
    abstract public function getRequiredFields() : array;
    abstract public function getItemRelationQuery(int $page=1) : QueryBuilder;
    abstract public function setFilesDefinitions();

    public function exportItemRelation()
    {
        $this->logger->info("BoxalinoExporter: Preparing products - START ITEM RELATIONS EXPORT.");
        $totalCount = 0; $page = 1; $header = true;
        while (Product::EXPORTER_LIMIT > $totalCount + Product::EXPORTER_STEP)
        {
            $query = $this->getItemRelationQuery($page);
            $count = $query->execute()->rowCount();
            $totalCount += $count;
            if ($totalCount == 0) {
                if($page==1) {
                    $this->logger->info("BoxalinoExporter: ITEM RELATIONS NOT FOUND FOR " . $this->getPropertyName());
                    $headers = $this->getItemRelationHeaderColumns();
                    $this->getFiles()->savePartToCsv($this->getItemRelationFile(), $headers);
                }
                break;
            }
            $data = $query->execute()->fetchAll();
            if (count($data) > 0 && $header) {
                $header = false;
                $data = array_merge(array(array_keys(end($data))), $data);
            }
            foreach(array_chunk($data, Product::EXPORTER_DATA_SAVE_STEP) as $dataSegment)
            {
                $this->getFiles()->savePartToCsv($this->getItemRelationFile(), $dataSegment);
            }

            $data = []; $page++;
            if($count < Product::EXPORTER_STEP - 1) { break;}
        }

        $this->setFilesDefinitions();
    }


    /**
     * @param $property
     * @return string
     */
    public function getItemRelationFileNameByProperty($property)
    {
        return "product_$property.csv";
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getRootCategoryId() : string
    {
        return $this->config->getChannelRootCategoryId($this->getAccount());
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getLanguageHeaders() : array
    {
        $languages = $this->config->getAccountLanguages($this->getAccount());
        $fields = preg_filter('/^/', 'value_', array_values($languages));

        return array_combine($languages, $fields);
    }

    /**
     * JOIN logic to access diverse localized fields/items values
     * If there is no translation available, the default one is used
     *
     * @param string $mainTable
     * @param string $mainTableIdField
     * @param string $idField
     * @param string $versionIdField
     * @param string $localizedFieldName
     * @param array $groupByFields
     * @return \Doctrine\DBAL\Query\QueryBuilder
     * @throws \Exception
     */
    public function getLocalizedFields(string $mainTable, string $mainTableIdField, string $idField,
                                       string $versionIdField, string $localizedFieldName, array $groupByFields
    ) : QueryBuilder {
        $languages = $this->config->getAccountLanguages($this->getAccount());
        $defaultLanguage = $this->getChannelDefaultLanguage();
        $alias = []; $innerConditions = []; $leftConditions = []; $selectFields = array_merge($groupByFields, []);
        $inner='inner'; $left='left';
        foreach($languages as $languageId=>$languageCode)
        {
            $t1 = $mainTable . "_" . $languageCode . "_" . $left;
            $t2 = $mainTable . "_" . $languageCode . "_" . $inner;
            $alias[$languageCode][$left] = $t1;
            $alias[$languageCode][$inner] = $t2;
            $selectFields[] = "IF(MIN($t1.$localizedFieldName) IS NULL, MIN($t2.$localizedFieldName), MIN($t1.$localizedFieldName)) as value_$languageCode";
            $innerConditions[$languageCode] = [
                "$mainTable.$mainTableIdField = $t2.$idField",
                "$mainTable.$versionIdField = $t2.$versionIdField",
                "LOWER(HEX($t2.language_id)) = '$defaultLanguage'"
            ];

            $leftConditions[$languageCode] = [
                "$mainTable.$mainTableIdField = $t1.$idField",
                "$mainTable.$versionIdField = $t1.$versionIdField",
                "LOWER(HEX($t1.language_id)) = '$languageId'"
            ];
        }

        $query = $this->connection->createQueryBuilder();
        $query->select($selectFields)
            ->from($mainTable);

        foreach($languages as $languageCode)
        {
            $query->innerJoin($mainTable, $mainTable, $alias[$languageCode][$inner], implode(" AND ", $innerConditions[$languageCode]))
                ->leftJoin($mainTable, $mainTable, $alias[$languageCode][$left], implode(" AND ", $leftConditions[$languageCode]));
        }

        $query->groupBy($groupByFields);
        return $query;
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function getLanguageHeaderConditional() : string
    {
        $conditional = [];
        foreach ($this->getLanguageHeaderColumns() as $column)
        {
            $conditional[]= "$column IS NOT NULL ";
        }

        return implode(" OR " , $conditional);
    }

    /**
     * @param $query
     * @return \Generator
     */
    public function processExport(QueryBuilder $query)
    {
        foreach($query->execute()->fetchAll() as $row)
        {
            yield $row;
        }
    }

    /**
     * @return string
     */
    public function getItemMainFile() : string
    {
        $callingClass = get_called_class();
        return $callingClass::EXPORTER_COMPONENT_ITEM_MAIN_FILE;
    }

    /**
     * @return string
     */
    public function getItemRelationFile() : string
    {
        $callingClass = get_called_class();
        return $callingClass::EXPORTER_COMPONENT_ITEM_RELATION_FILE;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getChannelId() : string
    {
        return $this->config->getAccountChannelId($this->getAccount());
    }

    /**
     * @return string
     */
    public function getPropertyName() : string
    {
        $callingClass = get_called_class();
        return $callingClass::EXPORTER_COMPONENT_ITEM_NAME;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getChannelDefaultLanguage() : string
    {
        return $this->config->getChannelDefaultLanguageId($this->getAccount());
    }

    /**
     * @return string
     */
    public function getAccount() : string
    {
        return $this->account;
    }

    /**
     * @param string $account
     * @return ItemsAbstract
     */
    public function setAccount(string $account)  :self
    {
        $this->account = $account;
        return $this;
    }

    /**
     * @return FileHandler
     */
    public function getFiles() : FileHandler
    {
        return $this->files;
    }

    /**
     * @param FileHandler $files
     * @return ItemsAbstract
     */
    public function setFiles(FileHandler $files) :self
    {
        $this->files = $files;
        return $this;
    }

    /**
     * @param ContentLibrary $library
     * @return ExporterComponentAbstract
     */
    public function setLibrary(ContentLibrary $library) :self
    {
        $this->library = $library;
        return $this;
    }

    /**
     * @return ContentLibrary
     */
    public function getLibrary() : ContentLibrary
    {
        return $this->library;
    }

    /**
     * @return array
     */
    public function getExportedProductIds() : array
    {
        return $this->exportedProductIds;
    }

    /**
     * @param array $ids
     * @return ItemsAbstract
     */
    public function setExportedProductIds(array $ids) :self
    {
        $this->exportedProductIds = $ids;
        return $this;
    }

    /**
     * @param array $additionalFields
     * @return array
     */
    public function getItemRelationHeaderColumns(array $additionalFields = []) : array
    {
        return [array_merge($additionalFields, [$this->getPropertyIdField(), "product_id"])];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getLanguageHeaderColumns() : array
    {
        return preg_filter('/^/', 'translation.', $this->getLanguageHeaders());
    }

    /**
     * @return string
     */
    public function getPropertyIdField() : string
    {
        return $this->getPropertyName().'_id';
    }
}
