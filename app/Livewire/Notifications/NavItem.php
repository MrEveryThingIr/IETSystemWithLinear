<?php

namespace App\Livewire\Notifications;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class NavItem extends Component
{
    #[On('notification-inbox-changed')]
    public function refreshUnreadCount(): void
    {
        // Rendering this request reconciles the durable database unread count.
    }

    public function render(): View
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return view('livewire.notifications.nav-item', [
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }
}
