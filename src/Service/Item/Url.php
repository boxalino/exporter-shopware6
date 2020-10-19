<?php
namespace Boxalino\Exporter\Service\Item;

use Boxalino\Exporter\Service\Component\ProductComponentInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Class Url
 * @TODO add storefront URL based on host/shop domain
 *
 * @package Boxalino\Exporter\Service\Item
 */
class Url extends ItemsAbstract
{
    CONST EXPORTER_COMPONENT_ITEM_NAME = "seo_url";
    CONST EXPORTER_COMPONENT_ITEM_MAIN_FILE = 'product_seo_url.csv';

    public function export()
    {
        $this->logger->info("BoxalinoExporter: Preparing products - START SEO URL EXPORT.");
        $this->config->setAccount($this->getAccount());
        $totalCount = 0; $page = 1; $header = true;
        while (ProductComponentInterface::EXPORTER_LIMIT > $totalCount + ProductComponentInterface::EXPORTER_STEP)
        {
            $query = $this->getItemRelationQuery($page);
            $count = $query->execute()->rowCount();
            $totalCount += $count;
            if ($totalCount == 0) {
                if($page==1) {
                    $headers = $this->getItemRelationHeaderColumns();
                    $this->getFiles()->savePartToCsv($this->getItemMainFile(), $headers);
                }
                break;
            }
            $data = $query->execute()->fetchAll();
            if ($header) {
                $header = false;
                $data = array_merge(array(array_keys(end($data))), $data);
            }

            foreach (array_chunk($data, ProductComponentInterface::EXPORTER_DATA_SAVE_STEP) as $dataSegment) {
                $this->getFiles()->savePartToCsv($this->getItemMainFile(), $dataSegment);
                $dataSegment = [];
            }

            $data = []; $page++;
            if ($count < ProductComponentInterface::EXPORTER_STEP - 1) { break; }
        }

        $this->setFilesDefinitions();
        $this->logger->info("BoxalinoExporter: Preparing products - END SEO URL.");
    }

    public function getItemRelationHeaderColumns(array $additionalFields = []): array
    {
        return [array_merge($this->getLanguageHeaders(), ['product_id'])];
    }

    public function getItemRelationQuery(int $page = 1): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($this->getRequiredFields())
            ->from('product')
            ->leftJoin('product', '( ' . $this->getLocalizedFieldsQuery()->__toString() . ') ',
                'seo_url', 'seo_url.foreign_key = product.id')
            ->andWhere('product.version_id = :live')
            ->addGroupBy('product.id')
            ->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION))
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

    public function setFilesDefinitions()
    {
        $attributeSourceKey = $this->getLibrary()->addCSVItemFile($this->getFiles()->getPath($this->getItemMainFile()), 'product_id');
        $this->getLibrary()->addSourceLocalizedTextField($attributeSourceKey, $this->getPropertyName(), $this->getLanguageHeaders());
    }

    /**
     * Prepare seo url joins
     * @return \Doctrine\DBAL\Query\QueryBuilder
     * @throws \Exception
     */
    protected function getLocalizedFieldsQuery() : QueryBuilder
    {
        return $this->getLocalizedFields('seo_url', 'id', 'id',
            'foreign_key','seo_path_info',
            ['seo_url.foreign_key', 'seo_url.sales_channel_id'],
            ["seo_url.route_name='frontend.detail.page'", "seo_url.is_canonical='1'", "LOWER(HEX(seo_url.sales_channel_id))='{$this->getChannelId()}'"]
        );
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getRequiredFields(): array
    {
        $translationFields = preg_filter('/^/', 'seo_url.', $this->getLanguageHeaders());
        return array_merge($translationFields, ['LOWER(HEX(product.id)) AS product_id']);
    }

}
