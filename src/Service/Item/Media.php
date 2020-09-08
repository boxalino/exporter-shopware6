<?php
namespace Boxalino\Exporter\Service\Item;

use Boxalino\Exporter\Service\Component\Product;
use Boxalino\Exporter\Service\Util\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaRepositoryDecorator;
use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaIdException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class Media
 * @package Boxalino\Exporter\Service\Item
 */
class Media extends ItemsAbstract
{
    CONST EXPORTER_COMPONENT_ITEM_NAME = "image";
    CONST EXPORTER_COMPONENT_ITEM_MAIN_FILE = 'images.csv';
    CONST EXPORTER_COMPONENT_ITEM_RELATION_FILE = 'product_image.csv';

    /**
     * @var UrlGeneratorInterface
     */
    protected $mediaUrlGenerator;

    /**
     * @var EntityRepositoryInterface
     */
    protected $mediaRepository;

    /**
     * @var Context
     */
    protected $context;

    /**
     * Media constructor.
     * @param Connection $connection
     * @param LoggerInterface $boxalinoLogger
     * @param Configuration $exporterConfigurator
     * @param UrlGeneratorInterface $generator
     * @param MediaRepositoryDecorator $mediaRepository
     */
    public function __construct(
        Connection $connection,
        LoggerInterface $boxalinoLogger,
        Configuration $exporterConfigurator,
        UrlGeneratorInterface $generator,
        EntityRepositoryInterface $mediaRepository
    ){
        $this->mediaRepository = $mediaRepository;
        $this->mediaUrlGenerator = $generator;
        $this->context = Context::createDefaultContext();
        parent::__construct($connection, $boxalinoLogger, $exporterConfigurator);
    }

    public function export()
    {
        $this->logger->info("BoxalinoExporter: Preparing products - START PRODUCT IMAGES EXPORT.");
        $totalCount = 0; $page = 1; $header = true; $data=[];
        while (Product::EXPORTER_LIMIT > $totalCount + Product::EXPORTER_STEP)
        {
            $query = $this->getItemRelationQuery($page);
            $count = $query->execute()->rowCount();
            $totalCount += $count;
            if ($totalCount == 0) {
                if($page==1) {
                    $this->logger->info("BoxalinoExporter: PRODUCTS EXPORT: No data found for images");
                    $headers = $this->getItemRelationHeaderColumns();
                    $this->getFiles()->savePartToCsv($this->getItemRelationFile(), $headers);
                }
                break;
            }
            $results = $this->processExport($query);
            foreach($results as $row)
            {
                $images = explode('|', $row[$this->getPropertyIdField()]);
                foreach ($images as $index => $image)
                {
                    try{
                        /** @var MediaEntity $media */
                        $media = $this->mediaRepository->search(new Criteria([$image]), $this->context)->get($image);
                        $images[$index] = $this->mediaUrlGenerator->getAbsoluteMediaUrl($media);
                    } catch(EmptyMediaFilenameException $exception)
                    {
                        $this->logger->info("Shopware: Media Path Export failed for $image: " . $exception->getMessage());
                    } catch(EmptyMediaIdException $exception)
                    {
                        $this->logger->info("Shopware: Media Path Export failed for $image: " . $exception->getMessage());
                    } catch(\Exception $exception)
                    {
                        $this->logger->warning("Shopware: Media Path Export failed for $image: " . $exception->getMessage());
                    }
                }
                $row[$this->getPropertyIdField()] = implode('|', $images);
                $data[] = $row;
            }

            if ($header) {
                $header = false;
                $data = array_merge($this->getItemRelationHeaderColumns(), $data);
            }

            foreach(array_chunk($data, Product::EXPORTER_DATA_SAVE_STEP) as $dataSegment)
            {
                $this->getFiles()->savePartToCsv($this->getItemRelationFile(), $dataSegment);
            }

            $data = []; $page++;
            if($count < Product::EXPORTER_STEP - 1) { break;}
        }

        $this->setFilesDefinitions();
        $this->logger->info("BoxalinoExporter: Preparing products - END IMAGES.");
    }

    public function setFilesDefinitions()
    {
        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath($this->getItemRelationFile()), 'product_id');
        $this->getLibrary()->addSourceStringField($attributeSourceKey, $this->getPropertyName(), $this->getPropertyIdField());
        $this->getLibrary()->addFieldParameter($attributeSourceKey, $this->getPropertyName(), 'splitValues', '|');
    }

    /**
     * @param int $page
     * @return QueryBuilder
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    public function getItemRelationQuery(int $page = 1): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($this->getRequiredFields())
            ->from("product_media")
            ->andWhere('product_media.product_version_id = :live')
            ->andWhere('product_media.version_id = :live')
            ->addGroupBy('product_media.product_id')
            ->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY)
            ->setFirstResult(($page - 1) * Product::EXPORTER_STEP)
            ->setMaxResults(Product::EXPORTER_STEP);

        return $query;
    }

    /**
     * @return array
     */
    public function getRequiredFields(): array
    {
        return [
            "GROUP_CONCAT(LOWER(HEX(product_media.media_id)) ORDER BY product_media.position SEPARATOR '|') AS {$this->getPropertyIdField()}",
            "LOWER(HEX(product_media.product_id)) AS product_id"
        ];
    }

}
