<?php

namespace App\Livewire\Messages;

use App\Models\Conversation;
use App\Models\User;
use App\Support\Messaging\Inbox;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/** The inbox: all conversations, search, and (on wide screens) the open conversation beside the list. */
#[Layout('layouts.app')]
class Index extends Component
{
    #[Url(as: 'c')]
    public string $open = '';

    #[Url(as: 'view', except: 'inbox')]
    public string $view = 'inbox';

    public string $search = '';

    #[On('inbox-changed')]
    public function refreshList(): void {}

    public function render(Inbox $inbox): View
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $conversation = null;
        if ($this->open !== '') {
            $conversation = Conversation::query()->where('uuid', $this->open)->first();
            if ($conversation === null || ! Gate::allows('view', $conversation->context()->first())) {
                $this->open = '';
                $conversation = null;
            }
        }

        $rows = $inbox->for($user, $this->view === 'archived');

        return view('livewire.messages.index', [
            'rows' => $rows,
            'conversation' => $conversation,
            'results' => trim($this->search) !== '' ? $inbox->search($user, $this->search) : collect(),
        ])->title(__('messages.title'));
    }
}
