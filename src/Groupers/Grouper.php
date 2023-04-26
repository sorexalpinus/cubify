<?php
namespace Cubify\Groupers;

/**
 * Interface Grouper
 *
 * Primary purpose of grouper is to produce grouping logic which can be used by various sql engines that do not provide CUBE functionality.
 * The output is always independent of sql flavour.
 *
 * @package Cubify\Groupers
 */
interface Grouper
{
    /**
     * Get groupings that have to be used in an sql query in order to cover all masks.
     * Example output:
     *      123  => rollup : (group by 1st,2nd and 3rd column in this order and use rollup)
     *      3    => flat : (group by 3rd column with no rollup)
     * @return array $groupings
     */
    public function getGroupings();

    /**
     * Get all possible masks that can be created for given number of dimensions
     * Output example:
     * Having 2, the masks are as follows: 11,10,01,00
     *
     * @param int $numDims
     * @return array $masks
     */
    public function getAllPossibleMasks($numDims);
}