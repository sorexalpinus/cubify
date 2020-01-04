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
    use Sql;

    const TOTAL = 'TOTAL';

    protected $totalKeyWord = 'TOTAL';
    protected $blankKeyWord = '(blank)';

    /** @var int $groupConcatMaxLength Maximum length of concatenated string for MySQL 32-bit version */
    protected $groupConcatMaxLength = 4294967295;

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
     * @throws CubifyException
     */
    public function __construct($connection, $baseQuery, $masks, $dimColumns, $measureColumns)
    {
        $this->connection = $connection;
        $this->baseQuery = $baseQuery;
        $this->masks = $masks;
        $this->dimColumns = $dimColumns;
        $this->measureColumns = $measureColumns;
        $this->setMysqlVars();
    }

    /**
     * In case GROUP_CONCAT MySQL function is used, set maximum length of concatenated string to maximum to prevent overflow.
     *
     * @return $this
     * @throws CubifyException
     */
    protected function setMysqlVars()
    {
        if (in_array('GROUP_CONCAT', $this->measureColumns)) {
            $query = $this->buildQuery('set_session_var', ['var' => 'group_concat_max_len', 'value' => $this->groupConcatMaxLength]);
            $result = $this->connection->query($query);
            if (!$result) {
                throw new CubifyException('Could not set mysql session variable group_concat_max_length');
            }
        }

        return $this;
    }


    /**
     * Get grouper object.
     *
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
     * Set grouper object.
     *
     * @param Grouper $grouper
     * @return $this
     */
    public function setGrouper($grouper)
    {
        $this->grouper = $grouper;
        return $this;
    }

    /**
     * Create and return cube query assembled from base query and set of defined dimensions, measures and masks.
     *
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

        return $this->buildQuery('main', [
            'maskHash' => $maskHash,
            'dimensions' => $dimCols,
            'measures' => $measureCols,
            'subQuery' => $subQuery,
            'groupingSequence' => $fullGrouping
        ]);
    }

    /**
     * Execute cube query and return resulting dataset.
     * If cube query is empty it is created and set in the process.
     *
     * @return $this
     * @throws CubifyException
     */
    public function runQuery()
    {
        $this->result = $this->connection->query($this->getCubeQuery());
        return $this;
    }

    /**
     * Count all possible combinations of dimensions values (cartesian product).
     * Additional sql queries are executed in the process.
     *
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
     * Return MysqlResult object for cube query.
     * If cube query is empty it is created and executed.
     *
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
     * Get final table-like dataset as an array.
     * If cube query is empty it is created and executed.
     *
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

    /**
     *
     * Replace NULL values with defined string (blank) in the base query.
     * This prevents the null values produced by base query to be mixed with NULL values produced by GROUP WITH ROLLUP (these represent totals)
     *
     * @param $baseQuery
     * @param $dimColumns
     * @param $measureColumns
     * @return mixed|string
     * @throws CubifyException
     */
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
        return $this->buildQuery('blanks', [
                'columns' => implode(',', $columns),
                'baseQuery' => $baseQuery
            ]
        );
    }

    /**
     * Assemble sub-query that generates results for particular subset of masks.
     *
     * @param string $baseQuery
     * @param int $position
     * @param array $groupSequence
     * @param string $groupType
     * @param array $dimColumns
     * @param array $measureColumns
     * @return mixed
     * @throws CubifyException
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
        return $this->buildQuery('sub', [
            'dimensions' => $selectDimensions,
            'measures' => $selectMeasures,
            'baseQuery' => $baseQuerySanitized,
            'groupingSequence' => $groupSequence,
            'withRollup' => $withRollup
        ]);
    }

    /**
     * Translate numeric sequence of columns to be grouped into sequence of actual column names.
     *
     * @param $groupings
     * @param $dimColumns
     * @return array
     */
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