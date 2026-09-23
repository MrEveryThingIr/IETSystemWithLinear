<?php
namespace App\Actions\Auth;
use App\Models\AccessInvitation;
use App\Models\AccessInvitationAcceptance;
use App\Models\Actor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class RegisterAccessInvitedUser
{
    public function __construct(private RegisterUser $registerUser) {}
    public function preview(string $token): AccessInvitation
    {
        $invitation=AccessInvitation::query()->with('inviter.user')->where('token',AccessInvitation::hashToken($token))->firstOrFail();
        abort_unless($invitation->isAvailable(),404);
        return $invitation;
    }
    /** @param array<string,mixed> $input */
    public function handle(array $input,string $token): User
    {
        return DB::transaction(function () use ($input,$token): User {
            $invitation=AccessInvitation::query()->where('token',AccessInvitation::hashToken($token))->lockForUpdate()->firstOrFail();
            abort_unless($invitation->isAvailable(),404);
            if ($invitation->email!==null && strcasecmp($invitation->email,(string)($input['email']??''))!==0) {
                throw ValidationException::withMessages(['email'=>__('access.email_mismatch')]);
            }
            $user=$this->registerUser->handle($input);
            $actor=Actor::query()->where('user_id',$user->id)->firstOrFail();
            AccessInvitationAcceptance::query()->create([
                'access_invitation_id'=>$invitation->id,
                'user_id'=>$user->id,
                'actor_id'=>$actor->id,
                'accepted_at'=>now(),
            ]);
            $invitation->forceFill(['uses_count'=>$invitation->uses_count+1])->save();
            return $user;
        },3);
    }
}
