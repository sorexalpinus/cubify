<?php

namespace Cubify\Groupers;

use Cubify\Exceptions\CubifyException;

/**
 * Trait Mask
 *
 * @package Cubify\Groupers
 */
trait Mask
{
    /**
     * Get all possible masks that can be created for given number of dimensions
     * Output example:
     * Having 2, the masks are as follows: 11,10,01,00
     *
     * @param int $numDims Number of dimensions
     * @return  array $masks
     */
    public function getAllPossibleMasks($numDims)
    {
        $masks = [];
        $array = range(1, $numDims);
        $powerSet = $this->getPowerSet($array);
        foreach ($powerSet as $combination) {
            $masks[] = $this->getMaskForCombination($combination);
        }
        arsort($masks);
        return $masks;
    }

    /**
     * Find permutations (all order combinations) of an array.
     * E.g.: 123 will result in (123,213,132,312,231,321)
     *
     * @param $return
     * @param $items
     * @param array $perms
     */
    public function permute(&$return, $items, $perms = [])
    {
        if (empty($items)) {
            $return[] = join('', $perms);
        } else {
            for ($i = count($items) - 1; $i >= 0; --$i) {
                $newItems = $items;
                $newPerms = $perms;
                list($foo) = array_splice($newItems, $i, 1);
                array_unshift($newPerms, $foo);
                $this->permute($return, $newItems, $newPerms);
            }
        }
    }

    /**
     * Get power set (all combinations regardless of order) for an array.
     * E.g. (1,2) will result in ((),(1),(2),(1,2))
     *
     * @param array $array
     * @return array $results
     */
    public function getPowerSet($array)
    {
        $results = [[]];
        foreach ($array as $element) {
            foreach ($results as $combination) {
                array_push($results, array_merge([$element], $combination));
            }
        }
        return $results;
    }

    /**
     * @param array $masks
     * @return int $numDims
     * @throws CubifyException
     */
    public function getNumberOfDimensions($masks) {
        if(is_array($masks) and sizeof($masks) > 0) {
            return strlen(reset($masks));
        }
        else {
            throw new CubifyException('Not able to determine number of dimensions; no masks provided');
        }
    }

    /**
     * With given number of dimensions find mask that represents finiest grouping (consists of pure "ones")
     * E.g. having 3 dimensions, the top level mask is 111
     *
     * @param int $numDims Number of dimensions.
     * @return string $maskHash
     */
    public function getAtomicMaskHash($numDims)
    {
        return str_repeat('1', $numDims);
    }
}