<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Push\Base64Url;
use App\Support\Push\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Devices (browsers, phones) that receive this person's notifications. */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
        ]);
        abort_unless(strlen(Base64Url::decode($data['keys']['p256dh'])) === 65 && strlen(Base64Url::decode($data['keys']['auth'])) >= 16, 422);

        $user = $this->user($request);
        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'user_id' => $user->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'user_agent' => Str::limit((string) $request->userAgent(), 290, ''),
                'failures' => 0,
            ],
        );

        return response()->json(['ok' => true, 'devices' => $user->pushSubscriptions()->count()]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint', '');
        $this->user($request)->pushSubscriptions()->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))->delete();

        return response()->json(['ok' => true]);
    }

    /** Sends a test notification to this person's devices right away. */
    public function test(Request $request, WebPushSender $sender): JsonResponse
    {
        $user = $this->user($request);
        $results = [];
        foreach ($user->pushSubscriptions()->get() as $subscription) {
            $result = $sender->send($subscription, [
                'title' => (string) __('notifications.device.test_title'),
                'body' => (string) __('notifications.device.test_body'),
                'url' => route('notifications.index'),
                'tag' => 'test',
            ], 600);
            if ($result === 'gone') {
                $subscription->delete();
            }
            $results[] = $result;
        }

        return response()->json(['ok' => in_array('sent', $results, true), 'results' => $results]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
