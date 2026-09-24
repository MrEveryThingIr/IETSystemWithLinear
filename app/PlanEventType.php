<?php

namespace App;

enum PlanEventType: string
{
    case Created = 'created';
    case ScheduleRuleCreated = 'schedule_rule_created';
    case ScheduleRuleCancelled = 'schedule_rule_cancelled';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
