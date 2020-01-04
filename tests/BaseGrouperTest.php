<?php

namespace Cubify\Tests;

use Cubify\Exceptions\CubifyException as CubifyExceptionAlias;
use Cubify\Groupers\BaseGrouper;
use PHPUnit\Framework\TestCase;

class BaseGrouperTest extends TestCase
{

    /**
     * @throws CubifyExceptionAlias
     */
    public function test__construct() {
        $grouper = new BaseGrouper(4,['1111','1100']);
        $this->assertInstanceOf(BaseGrouper::class,$grouper);
    }

    /**
     * @throws CubifyExceptionAlias
     */
    public function test__construct_exc()
    {
        $this->expectException(CubifyExceptionAlias::class);
        new BaseGrouper(4,['111','110']);
    }

    /**
     * @throws CubifyExceptionAlias
     */
    public function test__construct_exc2()
    {
        $this->expectException(CubifyExceptionAlias::class);
        new BaseGrouper(3,['11','110']);
    }

    /**
     * @return array
     */
    public function getGroupingsProvider() {
        return [
            [3,['111','001'],[312 => 'rollup']],
            [4,['1101','0011','1100','0101'],[1243 => 'rollup',34 => 'flat',24 => 'flat']]
        ];
    }

    /**
     * @dataProvider getGroupingsProvider
     * @param int $numDims
     * @param array $masks
     * @param array $groupings
     * @throws CubifyExceptionAlias
     */
    public function testGetGroupings($numDims,$masks,$groupings)
    {
        $grouper = new BaseGrouper($numDims,$masks);
        $this->assertSame($groupings,$grouper->getGroupings());
    }

    public function allMasksProvider() {
        return [
            [
                4,
                ['1111','1110','1100','1000','0000'],
                ['1111', '1110', '1101', '1100', '1011', '1010', '1001', '1000', '0111', '0110', '0101',
                    '0100', '0011', '0010', '0001', '0000']
            ],
            [
                3,
                ['111'],
                ['111','110','101','100','011','010','001','000']
            ]
        ];
    }

    /**
     * @dataProvider allMasksProvider
     * @param $numDims
     * @param $masks
     * @param $allMasks
     * @throws CubifyExceptionAlias
     */
    public function testGetAllMasks($numDims,$masks,$allMasks)
    {
        $grouper = new BaseGrouper($numDims,$masks);
        $this->assertSame($allMasks,array_values($grouper->getAllMasks()));
    }

}
