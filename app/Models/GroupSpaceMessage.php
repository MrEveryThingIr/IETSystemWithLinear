<?php

namespace App\Models;

use Database\Factories\GroupSpaceMessageFactory;

/**
 * @deprecated Phase 11 migrated GroupSpace chat into the Context Conversation kernel.
 *             Use ConversationMessage for new code.
 */
class GroupSpaceMessage extends ConversationMessage
{
    protected $table = 'conversation_messages';

    protected static function newFactory(): GroupSpaceMessageFactory
    {
        return GroupSpaceMessageFactory::new();
    }
}
