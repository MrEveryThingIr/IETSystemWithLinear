<?php

namespace App\Support;

use App\Jobs\DeliverNotificationOutbox;
use App\Models\Context;
use App\Models\NotificationOutbox;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationOutboxWriter
{
    /** @param array<string, mixed> $data */
    public function request(
        User $recipient,
        string $dedupeKey,
        string $kind,
        array $data,
        ?Context $context = null,
        ?string $subjectType = null,
        ?string $subjectUuid = null,
    ): NotificationOutbox {
        $current = User::query()->findOrFail($recipient->id);

        abort_unless(
            $current->status === 'active' && $current->email_verified_at !== null,
            422,
            'Notifications require an active verified recipient.',
        );

        $dedupeKey = Str::squish($dedupeKey);
        $kind = Str::squish($kind);

        abort_if($dedupeKey === '' || mb_strlen($dedupeKey) > 191, 422, 'Notification dedupe key is invalid.');
        abort_if($kind === '' || mb_strlen($kind) > 100, 422, 'Notification kind is invalid.');

        if (isset($data['url']) && is_string($data['url'])) {
            $data['url'] = $this->relativeUrl($data['url']);
        }

        /** @var NotificationOutbox $outbox */
        $outbox = NotificationOutbox::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'recipient_user_id' => $current->id,
                'kind' => $kind,
                'context_id' => $context?->id,
                'subject_type' => $subjectType,
                'subject_uuid' => $subjectUuid,
                'data' => $data,
                'requested_at' => now(),
            ],
        );

        abort_unless(
            (int) $outbox->recipient_user_id === (int) $current->id,
            409,
            'Notification dedupe key already belongs to another recipient.',
        );

        if ($outbox->dispatched_at === null && $outbox->discarded_at === null) {
            DeliverNotificationOutbox::dispatch($outbox->id)->onQueue('notifications');
        }

        return $outbox;
    }

    private function relativeUrl(string $url): string
    {
        if (str_starts_with($url, '/')) {
            abort_if(str_starts_with($url, '//'), 422, 'Notification URL is invalid.');

            return $url;
        }

        $parts = parse_url($url);
        abort_if($parts === false, 422, 'Notification URL is invalid.');

        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return ($path !== '' ? $path : '/').$query.$fragment;
    }
}
