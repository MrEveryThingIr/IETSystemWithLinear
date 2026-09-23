<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
class AccessInvitationAcceptance extends Model
{
    protected $fillable=['access_invitation_id','user_id','actor_id','accepted_at'];
    protected static function booted(): void
    {
        static::updating(function (): never { throw new LogicException('Access invitation acceptance evidence is immutable.'); });
        static::deleting(function (): never { throw new LogicException('Access invitation acceptance evidence is immutable.'); });
    }
    /** @return BelongsTo<AccessInvitation, $this> */
    public function invitation(): BelongsTo { return $this->belongsTo(AccessInvitation::class,'access_invitation_id'); }
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo { return $this->belongsTo(Actor::class); }
    protected function casts(): array { return ['accepted_at'=>'datetime']; }
}
