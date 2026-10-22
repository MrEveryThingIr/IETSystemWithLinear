<?php

namespace App\Support\Messaging;

use App\Models\Conversation;
use Illuminate\Support\Facades\DB;

/** A short fingerprint of what a conversation shows; it changes whenever the view would. */
class ThreadVersion
{
    public function for(Conversation $conversation): string
    {
        $id = (int) $conversation->id;
        $messages = DB::table('conversation_messages')->where('conversation_id', $id)->selectRaw('max(id) as last, count(*) as total')->first();
        $revisions = (int) DB::table('conversation_message_revisions as r')->join('conversation_messages as m', 'm.id', '=', 'r.conversation_message_id')
            ->where('m.conversation_id', $id)->max('r.id');
        $reactions = DB::table('conversation_message_reactions as x')->join('conversation_messages as m', 'm.id', '=', 'x.conversation_message_id')
            ->where('m.conversation_id', $id)->selectRaw('count(*) as total, max(x.id) as last')->first();
        $reads = (string) DB::table('conversation_participants')->where('conversation_id', $id)->max('last_read_message_id');

        return substr(sha1(implode(':', [$messages->last ?? 0, $messages->total ?? 0, $revisions, $reactions->total ?? 0, $reactions->last ?? 0, $reads])), 0, 16);
    }
}
