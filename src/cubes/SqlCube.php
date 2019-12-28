<?php

namespace Cubify\Cubes;

interface SqlCube
{
    public function runQuery();

    public function getResult();

    public function getCubeQuery();
}