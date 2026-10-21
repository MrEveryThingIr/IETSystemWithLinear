<?php

namespace App\Support\Push;

use App\Models\NotificationOutbox;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\ImportantReminderMail;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs from the scheduler (never inside a person's request, so pages stay
 * fast): sends every newly delivered notification to the recipient's
 * devices, and emails reminders of plans marked important.
 */
final class DevicePushDispatcher
{
    public function __construct(private readonly WebPushSender $sender) {}

    public function run(int $limit = 200): int
    {
        $pushed = 0;
        $rows = NotificationOutbox::query()
            ->whereNotNull('dispatched_at')
            ->whereNull('pushed_at')
            ->where('requested_at', '>=', now()->subDay())
            ->orderBy('id')
            ->limit(max(1, min($limit, 1000)))
            ->get();

        foreach ($rows as $row) {
            // Claim the row first so an overlapping run cannot send it twice.
            if (NotificationOutbox::query()->whereKey($row->id)->whereNull('pushed_at')->update(['pushed_at' => now()]) === 0) {
                continue;
            }

            $user = User::query()->find($row->recipient_user_id);
            if (! $user instanceof User || $user->status !== 'active') {
                continue;
            }

            $text = $this->text((array) $row->data, $user);
            foreach (PushSubscription::query()->where('user_id', $user->id)->get() as $subscription) {
                $result = $this->sender->send($subscription, [
                    'title' => $text['title'],
                    'body' => $text['body'],
                    'url' => $text['url'],
                    'tag' => $row->uuid,
                    'important' => (bool) data_get($row->data, 'important', false),
                ]);
                if ($result === 'gone') {
                    $subscription->delete();
                } elseif ($result === 'sent') {
                    $subscription->forceFill(['last_used_at' => now(), 'failures' => 0])->save();
                    $pushed++;
                } else {
                    $subscription->increment('failures');
                    if ($subscription->failures >= 20) {
                        $subscription->delete();
                    }
                }
            }

            if ($row->kind === 'planner.reminder' && (bool) data_get($row->data, 'important')) {
                try {
                    $user->notify((new ImportantReminderMail($text['title'], $text['body'], $text['url']))->locale($user->locale ?: config('app.locale')));
                } catch (Throwable $exception) {
                    Log::warning('Important reminder email failed.', ['outbox' => $row->uuid, 'error' => $exception->getMessage()]);
                }
            }
        }

        return $pushed;
    }

    /** @param array<string, mixed> $data @return array{title: string, body: string, url: string} */
    public function text(array $data, User $user): array
    {
        $locale = $user->locale ?: config('app.locale');
        $titleKey = is_string($data['title_key'] ?? null) ? $data['title_key'] : 'notifications.messages.generic_title';
        $bodyKey = is_string($data['body_key'] ?? null) ? $data['body_key'] : null;

        return [
            'title' => (string) __($titleKey, is_array($data['title_params'] ?? null) ? $data['title_params'] : [], $locale),
            'body' => $bodyKey !== null ? (string) __($bodyKey, is_array($data['body_params'] ?? null) ? $data['body_params'] : [], $locale) : '',
            'url' => is_string($data['url'] ?? null) ? $data['url'] : route('notifications.index'),
        ];
    }
}
