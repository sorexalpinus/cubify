<?php

namespace Cubify\Groupers;

use Cubify\Exceptions\CubifyException;

class BaseGrouper implements Grouper
{
    /** @var int $numDims number of dimensions */
    protected $numDims;
    /** @var array $masks all defined masks that we need to populate/calculate */
    protected $masks;

    /**
     * GroupingsGenerator constructor.
     *
     * @param int $numDims
     * @param array $masks
     * @throws CubifyException
     */
    public function __construct($numDims, $masks)
    {
        if($this->inputValid($numDims, $masks)) {
            $this->numDims = $numDims;
            $this->setMasks($masks);
        }
        else {
            throw new CubifyException('Invalid definition - inconsistency in number of dimensions and masks');
        }
    }


    /**
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
     * @return  array $masks
     */
    public function getAllMasks()
    {
        $masks = [];
        $array = range(1, $this->numDims);
        $powerSet = $this->getPowerSet($array);
        foreach ($powerSet as $key => $comb) {
            $masks[] = $this->getMaskForCombination($comb);
        }
        arsort($masks);
        return $masks;
    }

    /**
     * @param int $numDims
     * @param array $masks
     * @return bool $isValid
     */
    protected function inputValid($numDims, $masks) {
        $isValid = true;
        foreach($masks as $mask) {
            if(!preg_match('/^([0-1]+)$/',$mask) or strlen($mask) != $numDims) {
                $isValid = false;
            }
        }
        return $isValid;
    }

    /**
     * @param array|string $masks array of binary masks, e.g: [1111,1110,1011,0110] or "all" for all possible masks
     * @return $this
     */
    protected function setMasks($masks)
    {
        if (is_array($masks)) {
            $this->masks = $this->prepareMasksToCover($masks);
        } elseif (is_string($masks) and $masks == 'all') {
            $masks = $this->getAllMasks();
            $this->masks = $this->prepareMasksToCover($masks);
        }
        return $this;
    }

    /**
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
     * @return string $maskHash
     */
    protected function getTopLevelMask()
    {
        return str_repeat('1', $this->numDims);
    }

    /**
     * @param array $masks
     * @return array $masks
     */
    protected function prepareMasksToCover($masks)
    {
        $topLevelMask = $this->getTopLevelMask();
        if (!in_array($topLevelMask, $masks)) {
            $masks[] = $topLevelMask;
        }
        $m = [];
        foreach ($masks as $key => $val) {
            $m[$val] = array_sum(str_split($val));
        }
        arsort($m);
        $masks = array_keys($m);
        foreach ($masks as $k => $v) {
            $masks[$k] = (string)$v;
        }
        return $masks;
    }

    /**
     * @param string $grouping e.g. 2134
     * @return array $rollupBinarySet e.g: 1111,1110,1100,0100
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
     * @param string $mask
     * @return string $grouping
     */
    protected function getGroupingFromMask($mask)
    {
        $g = '';
        $mask = str_split($mask);
        foreach ($mask as $pos => $val) {
            if ($val == '1') {
                $g .= $pos + 1;
            }
        }
        return $g;
    }

    /**
     * @param $return
     * @param $items
     * @param array $perms
     * @return bool
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
        return true;
    }

    /**
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