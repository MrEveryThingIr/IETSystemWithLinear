<?php

namespace App;

enum ProfileIntentStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Closed = 'closed';
}
