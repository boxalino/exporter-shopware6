<?php
namespace Boxalino\Exporter\Service;

use Doctrine\DBAL\Query\QueryBuilder;

interface StateRecognitionInterface
{
    public function addState(QueryBuilder $query) : QueryBuilder;
}