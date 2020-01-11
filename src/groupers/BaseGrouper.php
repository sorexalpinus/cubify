<?php

namespace Cubify\Groupers;

use Cubify\Exceptions\CubifyException;

/**
 * Class BaseGrouper
 *
 * Basic implementation of grouper.
 *
 * @see Grouper interface
 * @package Cubify\Groupers
 */
class BaseGrouper implements Grouper
{
    /** @var int $numDims number of dimensions */
    protected $numDims;
    /** @var array $masks all defined masks that we need to populate/calculate */
    protected $masks;

    /**
     * GroupingsGenerator constructor.
     *
     * @param int $numDims Number of dimensions.
     * @param array|string $masks Masks to cover as an array or 'all' for all possible combinations.
     * @throws CubifyException
     */
    public function __construct($numDims, $masks)
    {
        if ($this->inputValid($numDims, $masks)) {
            $this->numDims = $numDims;
            if ($masks == 'all') {
                $masks = $this->getAllMasks();
            }
            $this->addTopLevelMask($masks);
            $this->sortMasks($masks);
            $this->masks = $masks;
        } else {
            throw new CubifyException('Invalid definition - inconsistency in number of dimensions and masks or other invalid input.');
        }
    }


    /**
     * Get groupings that have to be used in an sql query in order to cover all masks.
     * Example output:
     *      123  => rollup : (group by 1st,2nd and 3rd column in this order and use rollup)
     *      3    => flat : (group by 3rd column with no rollup)
     *
     * @return array $groupings
     */
    public function getGroupings()
    {
        if (is_array($this->masks) and sizeof($this->masks) > 0) {
            $masksToCover = $this->masks;
            $masksCovered = [];
            foreach ($masksToCover as $key => $val) {
                $masksCovered[$val] = 0;
            }
            foreach ($masksToCover as $cMask) {
                if (!$masksCovered[$cMask]) {
                    $result = $this->getOptimalGroupingsForMask($cMask, $masksCovered);
                    foreach ($result as $mask => $grouping) {
                        if (!$masksCovered[$mask]) {
                            $masksCovered[$mask] = $grouping;
                        }
                    }
                }
            }
            $counts = [];
            foreach ($masksCovered as $mask => $grouping) {
                if (!isset($counts[$grouping])) {
                    $counts[$grouping] = 0;
                }
                $counts[$grouping]++;
            }
            $masksCovered = [];
            foreach ($counts as $grouping => $num) {
                $masksCovered[$grouping] = $num > 1 ? 'rollup' : 'flat';
            }
            return $masksCovered;

        } else {
            return [];
        }
    }

    /**
     * Get all possible masks that can be created for given number of dimensions
     * Output example:
     * Having 2, the masks are as follows: 11,10,01,00
     *
     * @return  array $masks
     */
    public function getAllMasks()
    {
        $masks = [];
        $array = range(1, $this->numDims);
        $powerSet = $this->getPowerSet($array);
        foreach ($powerSet as $combination) {
            $masks[] = $this->getMaskForCombination($combination);
        }
        arsort($masks);
        return $masks;
    }

    /**
     * Validate user input consistency.
     *
     * @param int $numDims
     * @param array $masks
     * @return bool $isValid
     */
    protected function inputValid($numDims, $masks)
    {

        $isValid = true;
        if (is_array($masks)) {
            foreach ($masks as $mask) {
                $mask = (string)$mask;
                if (strlen($mask) > 0) {
                    if (!preg_match('/^([0-1]+)$/', $mask) or strlen($mask) != $numDims) {
                        $isValid = false;
                    }
                } else {
                    $isValid = false;
                }
            }
        } elseif (!is_string($masks) and $masks == 'all') {
            $isValid = true;
        } else {
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * Try to cover maximum masks with a single grouping
     *
     * Example: Mask 111 will be covered by grouping 123 and will also cover masks 110,100 and 000
     * Example output: [111=> 123, 110 => 123, 100 => 123]
     *
     * @param string $mask mask binary hash, e.g. 1101
     * @param array $masksCovered
     * @return array
     */
    protected function getOptimalGroupingsForMask($mask, $masksCovered)
    {
        $return = [];
        $masksToCover = [];
        foreach ($masksCovered as $key => $val) {
            if (!$val) {
                $masksToCover[] = $key;
            }
        }
        $rollupSets = $this->getPossibleGroupingsForMask($mask);
        $coverageScores = $overlapSets = [];
        //find out how many mask is able to cover each of the sets
        foreach ($rollupSets as $key => $candidateSet) {
            $overlap = array_intersect($candidateSet, $masksToCover);
            $coverageScores[$key] = count($overlap);
            $overlapSets[$key] = $overlap;
        }
        arsort($coverageScores);
        //pick the winner
        $scoreKeys = array_keys($coverageScores);
        $winner = reset($scoreKeys);
        foreach ($overlapSets[$winner] as $binaryMask) {
            $return[$binaryMask] = $winner;
        }
        return $return;
    }

    /**
     * Get all possible groupings that will cover a mask.
     * Example:
     * mask 11 will be covered by:
     * 12 - this will also cover 10 and 11
     * 21 - this will also cover 01 and 00
     *
     * @param string $mask
     * @return array $groupings
     */
    protected function getPossibleGroupingsForMask($mask)
    {
        $groupingSets = [];
        $grouping = $this->getGroupingFromMask($mask);
        $groupingArray = str_split($grouping);
        $permutations = [];
        $this->permute($permutations, $groupingArray);
        foreach ($permutations as $grouping) {
            $groupingSets[$grouping] = $this->getRollupMasks($grouping);
        }
        return $groupingSets;
    }

    /**
     * Get numbered grouping sequence from binary mask.
     * E.g.: mask 111 will result in 123
     *
     * @param string $mask
     * @return string $grouping
     */
    protected function getGroupingFromMask($mask)
    {
        $grouping = '';
        $mask = str_split($mask);
        foreach ($mask as $pos => $val) {
            if ($val == '1') {
                $grouping .= $pos + 1;
            }
        }
        return $grouping;
    }

    /**
     * Get masks that are covered by a grouping when used with ROLLUP
     * E.g.: grouping 12 will cover 11,10,00
     *
     * @param string $grouping
     * @return array $rollupMasks
     */
    protected function getRollupMasks($grouping)
    {
        $length = $this->numDims;
        $gArr = str_split($grouping);
        $base = str_repeat('0', $length);
        $rollupMasks = [];
        while (count($gArr) > 0) {
            $rollupItem = $base;
            foreach ($gArr as $pos) {
                $rollupItem[$pos - 1] = '1';
            }
            $rollupMasks[] = $rollupItem;
            array_pop($gArr);
        }
        $rollupMasks[] = $base;
        return $rollupMasks;
    }

    /**
     * Get mask that is covered by a single combination (grouping)
     * E.g. having 3 dimensions, combination (3,2) results in mask 011
     *
     * @param array $combination
     * @return string $mask
     */
    protected function getMaskForCombination($combination)
    {
        $base = str_repeat('0', $this->numDims);
        $mask = $base;
        foreach ($combination as $pos) {
            $mask[$pos - 1] = '1';
        }
        return $mask;
    }

    /**
     * With given number of dimensions find mask that represents finiest grouping (consists of pure "ones")
     * E.g. having 3 dimensions, the top level mask is 111
     *
     * @return string $maskHash
     */
    protected function getTopLevelMask()
    {
        return str_repeat('1', $this->numDims);
    }

    /**
     * Add top level mask into set of masks provided by user.
     *
     * @param $masks
     * @return $this
     */
    protected function addTopLevelMask(&$masks)
    {
        $topLevelMask = $this->getTopLevelMask();
        if (!in_array($topLevelMask, $masks)) {
            $masks[] = $topLevelMask;
        }
        return $this;
    }

    /**
     * Sort the masks so that those that require most column groupings are at the top.
     * Example output: 111,011,010,000
     *
     * @param array $masks
     * @return $this
     */
    protected function sortMasks(&$masks)
    {
        foreach ($masks as $key => $val) {
            $m[$val] = array_sum(str_split($val));
        }
        arsort($m);
        $masks = array_keys($m);
        foreach ($masks as $k => $v) {
            $masks[$k] = (string)$v;
        }
        return $this;
    }


    /**
     * Find permutations (all order combinations) of an array.
     * E.g.: 123 will result in (123,213,132,312,231,321)
     *
     * @param $return
     * @param $items
     * @param array $perms
     */
    protected function permute(&$return, $items, $perms = [])
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
    protected function getPowerSet($array)
    {
        $results = [[]];
        foreach ($array as $element) {
            foreach ($results as $combination) {
                array_push($results, array_merge([$element], $combination));
            }
        }
        return $results;
    }
}