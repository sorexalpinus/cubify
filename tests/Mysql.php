<?php
/**
 * Created by PhpStorm.
 * User: jhlatky
 * Date: 12. 9. 2019
 * Time: 12:46
 */

namespace Cubify\Tests;

use Cubify\Exceptions\CubifyException;
use mysqli;
use mysqli_result;

/**
 * Class MysqliExt
 *
 * @package Cubify
 */
class Mysql extends mysqli
{
    /** @var mysqli $instance */
    static protected $instance;

    /**
     * @param array $config
     * @return Mysql $mysql
     * @throws CubifyException
     */
    static public function getInstance($config)
    {
        if (!isset(self::$instance)) {
            $instance = new Mysql($config['host'], $config['user'], $config['password'], null, $config['port']);
            if ($instance) {
                $instance->init();
                self::$instance = $instance;
            } else {
                throw new CubifyException('Could not connect to DB. Please check your configuration.');
            }
        }
        return self::$instance;
    }

    /**
     * Checks if a database of the name provided exists within current server connection.
     *
     * @param string $dbName
     * @return bool $exists
     * @throws CubifyException
     */
    public function dbExists($dbName)
    {
        $query = 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = "' . $dbName . '"';
        $result = $this->query($query);
        if ($result) {
            if ($result->fetch_assoc()['SCHEMA_NAME']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Import sql database stored in a zipped file.
     *
     * @param string $filePath Zipped file.
     * @return bool
     * @throws CubifyException
     */
    public function importDb($filePath)
    {
        $p = explode(DS, $filePath);
        $dbName = str_replace('.zip', '', $p[count($p) - 1]);
        $zip = new \ZipArchive();
        if (file_exists($filePath) and $zip->open($filePath)) {
            $targetPath = CUBIFY_TESTS_PATH . DS . 'files' . DS;
            if (file_exists($targetPath)) {
                $zip->extractTo(CUBIFY_TESTS_PATH . DS . 'files' . DS);
                $zip->close();
                $extractedFilePath = $targetPath . $dbName . '.sql';
                $this->importFile($extractedFilePath);

            } else {
                throw new CubifyException(sprintf('Target folder %s not found', $targetPath));
            }

        } else {
            throw new CubifyException(sprintf('Could extract file %s', $filePath));
        }
        return true;
    }

    /**
     * Select a database.
     *
     * @param string $dbName
     * @return bool $result
     */
    public function selectDb($dbName)
    {
        return $this->select_db($dbName);
    }

    /**
     * Import an sql file, then delete it.
     *
     * @param string $filePath
     * @throws CubifyException
     */
    public function importFile($filePath)
    {
        if (file_exists($filePath)) {
            $this->multiQuery(file_get_contents($filePath));
            @unlink($filePath);
        } else {
            throw new CubifyException(sprintf('Extracted DB file %s not found', $filePath));
        }
    }


    /**
     * Initialize connection by setting utf/unicode character sets and collations.
     *
     * @return $this|mysqli
     * @throws CubifyException
     */
    public function init()
    {
        parent::init();
        $this->query('SET character_set_client=utf8');
        $this->query('SET character_set_connection=utf8');
        $this->query('SET character_set_results=utf8');
        $this->query('SET character_set_database=utf8');
        $this->query('SET character_set_server=utf8');
        $this->query('SET collation_connection=utf8_unicode_ci');
        $this->query('SET collation_database=utf8_unicode_ci');
        $this->query('SET collation_server=utf8_unicode_ci');
        $this->query('SET names utf8');
        return $this;
    }

    /**
     * Get a query for testing purposes.
     *
     * @param bool $inclNull
     * @return string
     */
    static public function getQuery($inclNull = false)
    {
        $query = '
            SELECT
                d.year_winter_starts as `Year`,
                d.snow_depth         as `Snow depth`,
                bt.name              as `Transect`,
                bs.name              as `Species`,
                d.num_animals        as `Num animals`
            FROM banff_data d
                     LEFT JOIN banff_species bs on d.species_id = bs.id
                     LEFT JOIN banff_transects bt on d.transect_id = bt.id
                     LEFT JOIN banff_transect_intervals bti on d.transect_interval_id = bti.id';
        $query .= ($inclNull) ? '' : ' WHERE d.species_id IS NOT NULL';
        return $query;
    }

    /**
     * Execute query and get result.
     *
     * @param string $query
     * @param null $resultMode
     * @return mysqli_result
     * @throws CubifyException
     */
    public function query($query, $resultMode = null)
    {
        $result = $this->real_query($query);
        if ($result) {
            return new mysqli_result($this);
        } else {
            throw new CubifyException(sprintf('Mysql error: <pre>%s</pre>', $query));
        }
    }

    /**
     * Execute multiple queries (or dump) and get result.
     *
     * @param string $query
     * @return mysqli_result
     * @throws CubifyException
     */
    public function multiQuery($query)
    {
        $result = $this->multi_query($query);
        if ($result) {
            return new mysqli_result($this);
        } else {
            throw new CubifyException(sprintf('Mysql error: <pre>%s</pre>', $query));
        }
    }

    /**
     * Executes a query and returns dataset in form of console table.
     *
     * @param string $query
     * @return string $table
     * @throws CubifyException
     */
    public function getConsoleTable($query) {

        $result = $this->query($query);
        if($result) {
            while($row = $result->fetch_assoc()) {
                $row = array_map(function($val) {return is_null($val)?'NULL':$val;},$row);
                $data[] = $row;
            }
            $tbl = new \Console_Table();
            $header = array_shift($data);
            $tbl->setHeaders(array_keys($header));
            foreach ($data as $id => $row) {
                $tbl->addRow($row);
            }
            $table = '<pre><code>' . $tbl->getTable() . '</code></pre>';
            return $table;
        }
    }
}