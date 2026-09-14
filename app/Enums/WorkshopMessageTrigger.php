<?php

namespace App\Enums;

enum WorkshopMessageTrigger: string
{
    case CorrectiveFollowUp = 'corrective_follow_up';
    case ScheduledRevision = 'scheduled_revision';
}
