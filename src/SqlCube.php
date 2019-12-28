<?php

namespace Cubify;

interface SqlCube
{
    public function runQuery();

    public function getResult();

    public function getCubeQuery();
}