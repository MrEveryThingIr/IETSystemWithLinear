<?php

namespace App\Actions\Auth;

use App\Actions\Groups\RedeemGroupInvitation;
use App\Models\Admission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterInvitedUser
{
    public function __construct(
        private RegisterUser $registerUser,
        private RedeemGroupInvitation $redeemInvitation,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{User, Admission}
     */
    public function handle(array $input, string $invitationToken): array
    {
        return DB::transaction(function () use ($input, $invitationToken): array {
            $this->redeemInvitation->preview($invitationToken);
            $user = $this->registerUser->handle($input);
            $admission = $this->redeemInvitation->execute($invitationToken, $user->actor, $user->email);

            return [$user, $admission];
        });
    }
}
