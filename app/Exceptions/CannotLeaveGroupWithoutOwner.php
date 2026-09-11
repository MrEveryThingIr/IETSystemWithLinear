<?php

namespace App\Exceptions;

use Exception;

class CannotLeaveGroupWithoutOwner extends Exception
{
    public function __construct()
    {
        parent::__construct(__('ui.messages.group_needs_owner'));
    }
}
