<?php

namespace App\Actions\Content;

use App\Models\Actor;
use App\Models\ContentInvitation;
use App\Models\SpaceContent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates a personal reading link for one invitee. Works for public and
 * private Content alike: the link is how a private piece reaches someone
 * without an account.
 */
class CreateContentInvitation
{
    public const MAX_ACTIVE_PER_CONTENT = 200;

    /** @var list<int> */
    public const EXPIRY_DAYS = [0, 1, 7, 30, 90, 365];

    public function execute(SpaceContent $content, User $user, string $inviteeName, ?string $message = null, int $expiresInDays = 0): ContentInvitation
    {
        $data = Validator::make(
            ['inviteeName' => trim($inviteeName), 'message' => $message !== null ? trim($message) : null, 'expiresInDays' => $expiresInDays],
            [
                'inviteeName' => ['required', 'string', 'max:120'],
                'message' => ['nullable', 'string', 'max:1000'],
                'expiresInDays' => ['required', 'integer', 'in:'.implode(',', self::EXPIRY_DAYS)],
            ],
            [],
            [
                'inviteeName' => __('public_content.invite.invitee_name'),
                'message' => __('public_content.invite.message'),
            ],
        )->validate();

        return DB::transaction(function () use ($content, $user, $data): ContentInvitation {
            $current = SpaceContent::query()->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('share', $current);

            $active = $current->invitations()->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count();
            if ($active >= self::MAX_ACTIVE_PER_CONTENT) {
                throw ValidationException::withMessages(['inviteeName' => __('public_content.errors.too_many_invitations', ['max' => self::MAX_ACTIVE_PER_CONTENT])]);
            }

            $actor = User::query()->with('actor')->findOrFail($user->id)->actor;
            abort_unless($actor instanceof Actor, 403);

            // Str::random is alphanumeric and cryptographically random.
            $token = Str::random(ContentInvitation::TOKEN_LENGTH);

            $days = (int) $data['expiresInDays'];

            return ContentInvitation::query()->create([
                'space_content_id' => $current->id,
                'created_by_actor_id' => $actor->id,
                'token_hash' => ContentInvitation::hashToken($token),
                'token_encrypted' => $token,
                'invitee_name' => $data['inviteeName'],
                'message' => ($data['message'] ?? '') !== '' ? $data['message'] : null,
                'expires_at' => $days > 0 ? now()->addDays($days) : null,
            ]);
        }, 3);
    }
}
