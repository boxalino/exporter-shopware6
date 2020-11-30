<?php
namespace Boxalino\Exporter\Service\Item;

use Boxalino\Exporter\Service\Component\ProductComponentInterface;
use Boxalino\Exporter\Service\ExporterConfigurationInterface;
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
 * Class MediaAbstract
 * Abstract model managing the export of product images relation
 *
 * @package Boxalino\Exporter\Service\Item
 */
abstract class MediaAbstract extends ItemsAbstract
{

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
     * @param ExporterConfigurationInterface $exporterConfigurator
     * @param UrlGeneratorInterface $generator
     * @param MediaRepositoryDecorator $mediaRepository
     */
    public function __construct(
        Connection $connection,
        LoggerInterface $boxalinoLogger,
        ExporterConfigurationInterface $exporterConfigurator,
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
        $this->logger->info("BoxalinoExporter: Preparing products - START PRODUCT MEDIA EXPORT for {$this->getPropertyName()}.");
        $this->config->setAccount($this->getAccount());
        $totalCount = 0; $page = 1; $header = true; $data=[];
        while (ProductComponentInterface::EXPORTER_LIMIT > $totalCount + ProductComponentInterface::EXPORTER_STEP)
        {
            $query = $this->getItemRelationQuery($page);
            $count = $query->execute()->rowCount();
            $totalCount += $count;
            if ($totalCount == 0) {
                if($page==1) {
                    $this->logger->info("BoxalinoExporter: PRODUCTS EXPORT: No data found for {$this->getPropertyName()}.");
                    $headers = $this->getItemRelationHeaderColumns();
                    $this->getFiles()->savePartToCsv($this->getItemRelationFile(), $headers);
                }
                break;
            }
            $results = $this->processExport($query);
            foreach($results as $row)
            {
                $data[] = $this->processMediaRow($row);
            }

            if ($header) {
                $header = false;
                $data = array_merge($this->getItemRelationHeaderColumns(), $data);
            }

            foreach(array_chunk($data, ProductComponentInterface::EXPORTER_DATA_SAVE_STEP) as $dataSegment)
            {
                $this->getFiles()->savePartToCsv($this->getItemRelationFile(), $dataSegment);
            }

            $data = []; $page++;
            if($count < ProductComponentInterface::EXPORTER_STEP - 1) { break;}
        }

        $this->setFilesDefinitions();
        $this->logger->info("BoxalinoExporter: Preparing products - END MEDIA for {$this->getPropertyName()}");
    }

    /**
     * @param array $row
     * @return array
     */
    abstract function processMediaRow(array $row) : array;

    /**
     * @param string|null $mediaId
     * @return string|null
     */
    public function getImageByMediaId(?string $mediaId) : ?string
    {
        $image = null;
        try{
            /** @var MediaEntity $media */
            $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->get($mediaId);
            $image = $this->mediaUrlGenerator->getAbsoluteMediaUrl($media);
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

        return $image;
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
            ->from("product")
            ->leftJoin('product','product_media', 'product_media',
                'product.product_media_id = product_media.id AND product_media.version_id=:live'
            )
            ->andWhere('product.version_id = :live')
            ->addGroupBy('product.id')
            ->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION), ParameterType::BINARY)
            ->setFirstResult(($page - 1) * ProductComponentInterface::EXPORTER_STEP)
            ->setMaxResults(ProductComponentInterface::EXPORTER_STEP);

        $productIds = $this->getExportedProductIds();
        if(!empty($productIds))
        {
            $query->andWhere('product.id IN (:ids)')
                ->setParameter('ids', Uuid::fromHexToBytesList($productIds), Connection::PARAM_STR_ARRAY);
        }

        return $query;
    }

}
