<?php

namespace Cubify;
/*
 new GroupGen($masks);
 */

use mysqli;
use mysqli_result;

/**
 * Class MysqlCube
 *
 * @package Cubify
 */
class MysqlCube implements SqlCube
{
    const TOTAL = 'TOTAL';

    /** @var GroupingsGenerator $groupingsGenerator */
    protected $groupingsGenerator;
    /** @var string $baseQuery */
    protected $baseQuery;
    /** @var array $dimColumns */
    protected $dimColumns;
    /** @var array $measureColumns */
    protected $measureColumns;
    /** @var mysqli $connection */
    protected $connection;
    /** @var bool|mysqli_result */
    protected $result;

    /**
     * MysqlCube constructor.
     *
     * @param mysqli $connection
     * @param GroupingsGenerator $groupingsGenerator
     * @param string $baseQuery
     * @param array $dimColumns
     * @param array $measureColumns
     */
    public function __construct($connection, $groupingsGenerator, $baseQuery, $dimColumns, $measureColumns)
    {
        $this->connection = $connection;
        $this->groupingsGenerator = $groupingsGenerator;
        $this->baseQuery = $baseQuery;
        $this->dimColumns = $dimColumns;
        $this->measureColumns = $measureColumns;
    }

    /**
     * @return string $subQueryTemplate
     */
    protected function getSubQueryTemplate()
    {
        return 'SELECT 
                     :dimensions,
                     :measures
                FROM (:baseQuery)
            GROUP BY :groupingSequence :withRollup';
    }

    /**
     * @return string $mainQueryTemplate
     */
    protected function getMainQueryTemplate()
    {
        return 'SELECT 
                     :maskHash,
                     :dimensions,
                     :measures           
                FROM (
                     :subQuery
                ) base
                GROUP BY :groupingSequence';
    }

    /**
     * @return string $cubeQuery
     */
    public function getCubeQuery()
    {

        $groupings = $this->groupingsGenerator->getGroupings('short');
        $namedGroupings = $this->getNamedGroupings($groupings, $this->dimColumns);

        $dimCols = [];
        foreach ($this->dimColumns as $column) {
            $dimCols[$column] = '`' . $column . '` AS `' . $column . '`';
        }
        $measureCols = [];
        foreach ($this->measureColumns as $column => $function) {
            $measureCols[$column] = $function . '(`' . $column . '`) AS `' . $column . '`';
        }
        $subQueries = [];
        foreach ($namedGroupings as $pos => $namedGrouping) {
            $groupSequence = explode(',', $namedGrouping['sequence']);
            $subQueries[$pos] = $this->getSubQuery($this->baseQuery, $pos, $groupSequence, $namedGrouping['type'], $dimCols, $measureCols);
        }
        $subQuery = implode(' UNION ', $subQueries);
        $dimCols = $measureCols = $maskHash = [];
        foreach ($this->dimColumns as $column) {
            $dimCols[] = 'IFNULL(`' . $column . '`, "' . self::TOTAL . '") AS `' . $column . '`';
            $maskHash[] = 'IF(`' . $column . '` IS NULL,0,1)';
        }
        $dimCols = implode(',', $dimCols);
        $maskHash = 'CONCAT(' . implode(',', $maskHash) . ') as `maskHash`';
        $measureCols = [];
        foreach ($this->measureColumns as $column => $function) {
            $measureCols[] = '`' . $column . '` AS `' . $column . '`';
        }
        $fullGrouping = [];
        foreach ($this->dimColumns as $column) {
            $fullGrouping[] = 'base.`'.$column.'`';
        }
        $fullGrouping = implode(',', $fullGrouping);
        $measureCols = implode(',', $measureCols);
        $mainQuery = str_replace(
            [':maskHash', ':dimensions', ':measures', ':subQuery', ':groupingSequence'],
            [$maskHash, $dimCols, $measureCols, $subQuery, $fullGrouping], $this->getMainQueryTemplate());
        return $mainQuery;
    }

    /**
     * @return $this
     */
    public function runQuery()
    {
        $this->result = $this->connection->query($this->getCubeQuery());
        return $this;
    }

    /**
     * @return bool|mysqli_result
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param string $baseQuery
     * @param int $position
     * @param array $groupSequence
     * @param string $groupType
     * @param array $dimColumns
     * @param array $measureColumns
     * @return mixed
     */
    protected function getSubQuery($baseQuery, $position, $groupSequence, $groupType, $dimColumns, $measureColumns)
    {
        $useDimCols = [];
        foreach ($dimColumns as $colName => $fullDef) {
            $useDimCols[] = in_array($colName, $groupSequence) ? $fullDef : 'NULL as `' . $colName . '`';
        }
        $selectDimensions = implode(',', $useDimCols);
        $selectMeasures = implode(',', $measureColumns);
        $withRollup = $groupType == 'rollup' ? ' WITH ROLLUP ' : '';
        $baseQueryWrapped = '(' . $baseQuery . ') baseQuery' . ($position + 1) . ' ';
        $groupSequence = implode(',',$groupSequence);
        return str_replace(
            [':dimensions', ':measures', ':baseQuery', ':groupingSequence', ':withRollup'],
            [$selectDimensions, $selectMeasures, $baseQueryWrapped, $groupSequence, $withRollup],
            $this->getSubQueryTemplate()
        );
    }

    protected function getNamedGroupings($groupings, $dimColumns)
    {
        $namedGroupings = [];
        foreach ($groupings as $grouping => $type) {
            $grouping = str_split($grouping);
            $namedGrouping = [];
            foreach ($grouping as $position) {
                $namedGrouping[$position] = $dimColumns[$position - 1];
            }
            $namedGrouping = implode(',', $namedGrouping);
            $namedGroupings[] = ['sequence' => $namedGrouping, 'type' => $type];
        }
        return $namedGroupings;
    }
}