<?php
namespace Cubify\Exceptions;

use Exception;

class CubifyException extends Exception
{
    public function __construct($message = "", $code = 0, $previous = null) {
        parent::__construct($message = "", $code = 0,  $previous = null);
        $this->message = 'Cubify: ' . $this->message;
    }
}