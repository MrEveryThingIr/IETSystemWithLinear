<?php
namespace App\Models;
use Database\Factories\AccessInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;
class AccessInvitation extends Model
{
    /** @use HasFactory<AccessInvitationFactory> */
    use HasFactory;
    protected $fillable=['invited_by_actor_id','email','token','expires_at','max_uses','uses_count','revoked_at'];
    protected $hidden=['token'];
    private ?string $plainTextToken=null;
    protected static function booted(): void
    {
        static::creating(function (self $invitation): void { $invitation->uuid ??= (string) Str::uuid(); });
        static::deleting(function (): never { throw new LogicException('Access invitation history is preserved.'); });
    }
    public static function issueToken(): string { return Str::random(64); }
    public static function hashToken(string $token): string { return hash('sha256',$token); }
    public function setTokenAttribute(string $token): void
    {
        $this->plainTextToken=$token;
        $this->attributes['token']=self::hashToken($token);
    }
    public function getTokenAttribute(?string $value): ?string { return $this->plainTextToken; }
    public function plainTextToken(): ?string { return $this->plainTextToken; }
    public function getRouteKeyName(): string { return 'uuid'; }
    public function isAvailable(): bool
    {
        return $this->revoked_at===null && $this->expires_at?->isPast()!==true && $this->uses_count<$this->max_uses;
    }
    public function state(): string
    {
        return match (true) {
            $this->revoked_at!==null=>'revoked',
            $this->expires_at?->isPast()===true=>'expired',
            $this->uses_count>=$this->max_uses=>'exhausted',
            default=>'available',
        };
    }
    public function maskedEmail(): ?string
    {
        if ($this->email===null) { return null; }
        [$local,$domain]=array_pad(explode('@',$this->email,2),2,'');
        return Str::substr($local,0,1).str_repeat('•',max(3,Str::length($local)-1)).'@'.$domain;
    }
    /** @return BelongsTo<Actor, $this> */
    public function inviter(): BelongsTo { return $this->belongsTo(Actor::class,'invited_by_actor_id'); }
    /** @return HasMany<AccessInvitationAcceptance, $this> */
    public function acceptances(): HasMany { return $this->hasMany(AccessInvitationAcceptance::class); }
    protected function casts(): array
    {
        return ['expires_at'=>'datetime','revoked_at'=>'datetime','max_uses'=>'integer','uses_count'=>'integer'];
    }
}
