<?php

namespace App\Actions\Auth;

use App\Actions\Groups\RedeemGroupInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterInvitedUser
{
    public function __construct(
        private RegisterUser $registerUser,
        private RedeemGroupInvitation $redeemInvitation,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(array $input, string $invitationToken): User
    {
        return DB::transaction(function () use ($input, $invitationToken): User {
            $invitation = $this->redeemInvitation->preview($invitationToken);
            if ($invitation->email !== null && strcasecmp($invitation->email, (string) ($input['email'] ?? '')) !== 0) {
                throw ValidationException::withMessages(['email' => __('ui.messages.invitation_email_mismatch')]);
            }

            return $this->registerUser->handle($input);
        });
    }
}
