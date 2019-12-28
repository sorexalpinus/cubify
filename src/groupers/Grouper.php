<?php
namespace Cubify\Groupers;

interface Grouper
{
    /**
     * @param string $output 'detailed','short'
     * @return array $groupings
     */
    public function getGroupings($output = 'short');

    /**
     * @return  array $masks
     */
    public function getAllMasks();
}