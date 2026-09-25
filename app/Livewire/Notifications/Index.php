<?php

namespace App\Livewire\Notifications;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Notifications')]
class Index extends Component
{
    #[Url]
    public string $filter = 'unread';

    #[On('notification-inbox-changed')]
    public function refreshInbox(): void
    {
        // Rendering this Livewire request reconciles the durable database inbox.
    }

    public function markRead(string $id): void
    {
        $this->notification($id)->markAsRead();
    }

    public function markAllRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function open(string $id): void
    {
        $notification = $this->notification($id);
        $notification->markAsRead();

        $data = $notification->data;
        $url = is_string($data['url'] ?? null) ? $data['url'] : route('notifications.index');

        abort_unless(
            str_starts_with($url, '/') && ! str_starts_with($url, '//'),
            422,
            'Notification destination is invalid.',
        );

        $this->redirect($url, navigate: true);
    }

    public function render(): View
    {
        if (! in_array($this->filter, ['unread', 'all'], true)) {
            $this->filter = 'unread';
        }

        $query = $this->filter === 'unread'
            ? $this->user()->unreadNotifications()
            : $this->user()->notifications();

        return view('livewire.notifications.index', [
            'notifications' => $query->latest('created_at')->limit(100)->get(),
            'unreadCount' => $this->user()->unreadNotifications()->count(),
        ]);
    }

    private function notification(string $id): DatabaseNotification
    {
        abort_unless(Str::isUuid($id), 404);

        /** @var DatabaseNotification|null $notification */
        $notification = $this->user()->notifications()->whereKey($id)->first();
        abort_unless($notification instanceof DatabaseNotification, 404);

        return $notification;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
