<?php
namespace App\Http\Controllers;
use App\Models\AccessInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
class AccessInvitationController
{
    public function show(Request $request,string $token): Response
    {
        $invitation=AccessInvitation::query()->with('inviter.user')->where('token',AccessInvitation::hashToken($token))->firstOrFail();
        return response()->view('access-invitations.show',[
            'invitation'=>$invitation,
            'token'=>$token,
            'state'=>$invitation->state(),
        ])->withHeaders(['Cache-Control'=>'private, no-store','Referrer-Policy'=>'no-referrer']);
    }
}
