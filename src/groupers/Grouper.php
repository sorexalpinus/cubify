<?php
namespace Cubify\Groupers;

interface Grouper
{
    /**
     * @return array $groupings
     */
    public function getGroupings();

    /**
     * @return  array $masks
     */
    public function getAllMasks();
}