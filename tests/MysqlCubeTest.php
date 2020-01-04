<?php

namespace Cubify\Tests;

use Cubify\Cubes\MysqlCube;
use Cubify\Exceptions\CubifyException;
use mysqli_result;
use PHPUnit\Framework\TestCase;

/**
 * Class MysqlCubeTest
 *
 * @package Cubify\Tests
 */
class MysqlCubeTest extends TestCase
{

    /** @var MysqlCube */
    private $cube;


    public function setUp()
    {
        try {
            $config = require CUBIFY_TESTS_PATH . DS . 'default.config.php';
            $dbName = $config['db'];
            $conn = Mysql::getInstance($config);

            if (!$conn->dbExists($dbName)) {
                $zipFilePath = CUBIFY_TESTS_PATH . DS . 'files' . DS . $dbName . '.zip';
                $conn->importDb($zipFilePath);
            }
            $conn->selectDb($dbName);
            $query = Mysql::getQuery(false);
            $masks = ['111', '110', '101'];
            $dims = ['Transect', 'Year', 'Species'];
            $measures = ['Snow depth' => 'GROUP_CONCAT', 'Num animals' => 'GROUP_CONCAT'];
            $this->cube = new MysqlCube($conn, $query, $masks, $dims, $measures);

        } catch (CubifyException $e) {
            echo $e->getMessage();
        }

    }

    public function test__construct()
    {
        $this->assertInstanceOf(MysqlCube::class, $this->cube);
    }

    /**
     * @throws CubifyException
     */
    public function testGetCubeQuery()
    {
        $query = $this->cube->getCubeQuery();
        $this->assertIsString($query);
        $this->assertNotEmpty($query);
        $this->assertStringContainsString('SELECT',$query);
        $this->assertEquals($this->getExpectedQuery(),str_replace('\r\n','',$query));
    }

    /**
     * @throws CubifyException
     */
    public function testGetResult()
    {
        $result = $this->cube->getResult();
        $this->assertInstanceOf(mysqli_result::class,$result);
        $this->assertSame(6,$result->field_count);
        $this->assertSame(357,$result->num_rows);
    }

    /**
     * @throws CubifyException
     */
    public function testGetResultDataset()
    {
        $dataset = $this->cube->getResultDataset();
        $this->assertIsArray($dataset);
        $this->assertNotEmpty($dataset);
        $this->assertCount(357,$dataset);
    }

    /**
     * @throws CubifyException
     */
    public function testGetCartesianCount()
    {
        $cartesianCount = $this->cube->getCartesianCount();
        $this->assertIsInt($cartesianCount);
        $this->assertSame(605,$cartesianCount);
    }

    /**
     * @return string $expectedQuery
     */
    public function getExpectedQuery() {
        return 'SELECT
    CONCAT(IF(`Transect` IS NULL,0,1),IF(`Year` IS NULL,0,1),IF(`Species` IS NULL,0,1)) as `maskHash`,
    IFNULL(`Transect`, "TOTAL") AS `Transect`,IFNULL(`Year`, "TOTAL") AS `Year`,IFNULL(`Species`, "TOTAL") AS `Species`,
    `Snow depth` AS `Snow depth`,`Num animals` AS `Num animals`
FROM (
         SELECT
    `Transect` AS `Transect`,`Year` AS `Year`,`Species` AS `Species`,
    GROUP_CONCAT(`Snow depth`) AS `Snow depth`,GROUP_CONCAT(`Num animals`) AS `Num animals`
FROM ((SELECT
    IFNULL(`Transect`,\'(blank)\') AS `Transect`,IFNULL(`Year`,\'(blank)\') AS `Year`,IFNULL(`Species`,\'(blank)\') AS `Species`,`Snow depth` AS `Snow depth`,`Num animals` AS `Num animals`
FROM ((
            SELECT
                d.year_winter_starts as `Year`,
                d.snow_depth         as `Snow depth`,
                bt.name              as `Transect`,
                bs.name              as `Species`,
                d.num_animals        as `Num animals`
            FROM banff_data d
                     LEFT JOIN banff_species bs on d.species_id = bs.id
                     LEFT JOIN banff_transects bt on d.transect_id = bt.id
                     LEFT JOIN banff_transect_intervals bti on d.transect_interval_id = bti.id WHERE d.species_id IS NOT NULL) baseQuery1 )) baseQuery01 )
GROUP BY Transect,Year,Species  WITH ROLLUP  UNION SELECT
    `Transect` AS `Transect`,NULL as `Year`,`Species` AS `Species`,
    GROUP_CONCAT(`Snow depth`) AS `Snow depth`,GROUP_CONCAT(`Num animals`) AS `Num animals`
FROM ((SELECT
    IFNULL(`Transect`,\'(blank)\') AS `Transect`,IFNULL(`Year`,\'(blank)\') AS `Year`,IFNULL(`Species`,\'(blank)\') AS `Species`,`Snow depth` AS `Snow depth`,`Num animals` AS `Num animals`
FROM ((
            SELECT
                d.year_winter_starts as `Year`,
                d.snow_depth         as `Snow depth`,
                bt.name              as `Transect`,
                bs.name              as `Species`,
                d.num_animals        as `Num animals`
            FROM banff_data d
                     LEFT JOIN banff_species bs on d.species_id = bs.id
                     LEFT JOIN banff_transects bt on d.transect_id = bt.id
                     LEFT JOIN banff_transect_intervals bti on d.transect_interval_id = bti.id WHERE d.species_id IS NOT NULL) baseQuery2 )) baseQuery02 )
GROUP BY Transect,Species 
         ) base
GROUP BY base.`Transect`,base.`Year`,base.`Species`';
    }
}
