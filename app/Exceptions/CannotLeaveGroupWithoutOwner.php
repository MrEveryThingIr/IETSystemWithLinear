<?php

namespace App\Exceptions;

use Exception;

class CannotLeaveGroupWithoutOwner extends Exception
{
    public function __construct()
    {
        parent::__construct('A Group must have at least one active Owner.');
    }
}
