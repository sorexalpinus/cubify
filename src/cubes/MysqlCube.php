<?php

namespace Cubify\Cubes;

use Cubify\Exceptions\CubifyException;
use Cubify\Groupers\BaseGrouper;
use Cubify\Groupers\Grouper;
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

    /** @var Grouper $grouper */
    protected $grouper;
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
    /** @var array $masks */
    protected $masks;

    /**
     * MysqlCube constructor.
     *
     * @param mysqli $connection
     * @param string $baseQuery
     * @param array $masks
     * @param array $dimColumns
     * @param array $measureColumns
     */
    public function __construct($connection, $baseQuery, $masks, $dimColumns, $measureColumns)
    {
        $this->connection = $connection;
        $this->baseQuery = $baseQuery;
        $this->masks = $masks;
        $this->dimColumns = $dimColumns;
        $this->measureColumns = $measureColumns;
    }

    /**
     * @return Grouper $grouper
     * @throws CubifyException
     */
    public function getGrouper()
    {
        if (!isset($this->grouper)) {
            $this->grouper = new BaseGrouper(count($this->dimColumns), $this->masks);
        }
        return $this->grouper;
    }

    /**
     * @param Grouper $grouper
     * @return $this
     */
    public function setGrouper($grouper)
    {
        $this->grouper = $grouper;
        return $this;
    }

    /**
     * @return string $cubeQuery
     * @throws CubifyException
     */
    public function getCubeQuery()
    {

        $groupings = $this->getGrouper()->getGroupings('short');
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
            $fullGrouping[] = 'base.`' . $column . '`';
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
     * @throws CubifyException
     */
    public function runQuery()
    {
        $this->result = $this->connection->query($this->getCubeQuery());
        return $this;
    }

    /**
     * @return int $cartesianCount
     * @throws CubifyException
     */
    public function getCartesianCount()
    {
        $countTemplate = 'SELECT COUNT(DISTINCT :column) AS dimCount FROM (:baseQuery) base';
        $dimCount = [];
        foreach ($this->dimColumns as $dimColumn) {
            $dimCountQuery = str_replace([':column', ':baseQuery'], [$dimColumn, $this->baseQuery], $countTemplate);
            $result = $this->connection->query($dimCountQuery);
            if ($result) {
                $row = $result->fetch_assoc();
                $dimCount[] = $row['dimCount'];
            } else {
                throw new CubifyException('Dimension count query failed');
            }
        }
        $cartesianCount = 0;
        foreach ($this->masks as $mask) {
            $mComb = 1;
            $m = str_split($mask);
            foreach ($m as $key => $bin) {
                $mComb *= $bin ? $dimCount[$key] : 1;
            }
            $cartesianCount += $mComb;
        }
        return $cartesianCount;
    }


    /**
     * @return bool|mysqli_result
     * @throws CubifyException
     */
    public function getResult()
    {
        if (!isset($this->result)) {
            $this->runQuery();
        }
        if (isset($this->result)) {
            return $this->result;
        } else {
            throw new CubifyException('Could not provide MySQL result - the result is empty');
        }

    }

    /**
     * @return array
     * @throws CubifyException
     */
    public function getResultDataset()
    {
        $ds = [];
        $result = $this->getResult();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $ds[] = $row;
            }
        }
        return $ds;
    }

    protected function getBlankSanitizedQueryTemplate()
    {
        return 'SELECT 
                     :columns
                FROM (:baseQuery)';
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

    protected function sanitizeBlanks($baseQuery, $dimColumns, $measureColumns)
    {
        $columns = [];
        if (is_array($dimColumns)) {
            foreach (array_keys($dimColumns) as $colName) {
                $columns[] = 'IFNULL(`' . $colName . '`,\'(blank)\') AS `' . $colName . '`';
            }
        }
        if (is_array($measureColumns)) {
            foreach (array_keys($measureColumns) as $colName) {
                $columns[] = '`' . $colName . '` AS `' . $colName . '`';
            }
        }
        $sanitizedQuery = '';
        if (sizeof($columns) > 0) {
            $columns = implode(',', $columns);
            $sanitizedQuery = str_replace([':columns', ':baseQuery'], [$columns, $baseQuery], $this->getBlankSanitizedQueryTemplate());
        }
        return $sanitizedQuery;
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
        $baseQuerySanitized = '(' . $this->sanitizeBlanks($baseQueryWrapped, $dimColumns, $measureColumns) . ') baseQuery0' . ($position + 1) . ' ';
        $groupSequence = implode(',', $groupSequence);
        return str_replace(
            [':dimensions', ':measures', ':baseQuery', ':groupingSequence', ':withRollup'],
            [$selectDimensions, $selectMeasures, $baseQuerySanitized, $groupSequence, $withRollup],
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