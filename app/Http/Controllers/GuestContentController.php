<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\Asset;
use App\Models\AssetMediaTrack;
use App\Models\AssetMediaVariant;
use App\Models\ContentInvitation;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Support\AssetHttpResponse;
use App\Support\ContentKinds;
use App\Support\ContentReaderStyle;
use App\Support\Media\MediaPresentation;
use App\Support\PublicContent\ContentExcerpt;
use App\Support\PublicContent\GuestContentAccess;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content read by guests: the public index, public posts, and invitation links.
 * Works the same whether the visitor is signed in or not; every decision goes
 * through GuestContentAccess.
 */
class GuestContentController extends Controller
{
    public function __construct(private readonly GuestContentAccess $access) {}

    public function index(Request $request): View
    {
        abort_unless((bool) config('public_content.index'), 404);

        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $kind = (string) $request->query('kind', '');
        $kindSlug = ContentKinds::slugFor($kind);

        $contents = self::publicQuery()
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $query->whereHas('activeRevision', fn ($revision) => $revision->where('title', 'like', $like));
            })
            ->when($kindSlug !== null, fn ($query) => $query->whereHas('blueprintVersion.blueprint', fn ($blueprint) => $blueprint->where('slug', $kindSlug)))
            ->with(['activeRevision.assets', 'author.user', 'author.profile', 'blueprintVersion.blueprint'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate((int) config('public_content.per_page', 12))
            ->withQueryString();

        return view('public-content.index', [
            'contents' => $contents,
            'cards' => self::cards($contents->getCollection()->all(), $this->access),
            'search' => $search,
            'kind' => $kindSlug !== null ? $kind : '',
            'kinds' => array_keys(ContentKinds::KINDS),
        ]);
    }

    public function show(SpaceContent $content): View
    {
        abort_unless($this->access->isPublic($content), 404);

        return $this->reader($content, null);
    }

    public function invitation(string $token): Response
    {
        $invitation = $this->invitationOrFail($token, allowUnusable: true);
        $root = $invitation->content()->first();

        if (! $invitation->isUsable() || ! $root instanceof SpaceContent || ! $this->access->viaInvitation($invitation, $root)) {
            $invitation->loadMissing('creator.user');

            return response()->view('public-content.invitation-unavailable', [
                'invitation' => $invitation,
                'reason' => $invitation->isRevoked() ? 'revoked' : ($invitation->isExpired() ? 'expired' : 'unavailable'),
            ], 410)->withHeaders($this->invitationHeaders());
        }

        $this->recordView($invitation);

        return response($this->reader($root, $invitation)->render())->withHeaders($this->invitationHeaders());
    }

    public function invitationContent(string $token, SpaceContent $content): Response
    {
        $invitation = $this->invitationOrFail($token);
        abort_unless($this->access->viaInvitation($invitation, $content), 404);

        if ((int) $invitation->space_content_id === (int) $content->id) {
            return redirect()->to($invitation->url());
        }

        $this->recordView($invitation);

        return response($this->reader($content, $invitation)->render())->withHeaders($this->invitationHeaders());
    }

    public function publicAsset(SpaceContent $content, Asset $asset): Response
    {
        $this->authorizeAsset($content, $asset, null);

        return AssetHttpResponse::inline($asset);
    }

    public function publicAssetDownload(SpaceContent $content, Asset $asset): Response
    {
        $this->authorizeAsset($content, $asset, null);

        return AssetHttpResponse::download($asset);
    }

    public function publicVariant(SpaceContent $content, Asset $asset, AssetMediaVariant $variant): Response
    {
        $this->authorizeAsset($content, $asset, null);

        return $this->variantResponse($asset, $variant);
    }

    public function publicTrack(SpaceContent $content, Asset $asset, AssetMediaTrack $track): Response
    {
        $this->authorizeAsset($content, $asset, null);

        return $this->trackResponse($asset, $track);
    }

    public function invitationAsset(string $token, SpaceContent $content, Asset $asset): Response
    {
        $this->authorizeAsset($content, $asset, $this->invitationOrFail($token));

        return $this->noIndex(AssetHttpResponse::inline($asset));
    }

    public function invitationAssetDownload(string $token, SpaceContent $content, Asset $asset): Response
    {
        $this->authorizeAsset($content, $asset, $this->invitationOrFail($token));

        return $this->noIndex(AssetHttpResponse::download($asset));
    }

    public function invitationVariant(string $token, SpaceContent $content, Asset $asset, AssetMediaVariant $variant): Response
    {
        $this->authorizeAsset($content, $asset, $this->invitationOrFail($token));

        return $this->noIndex($this->variantResponse($asset, $variant));
    }

    public function invitationTrack(string $token, SpaceContent $content, Asset $asset, AssetMediaTrack $track): Response
    {
        $this->authorizeAsset($content, $asset, $this->invitationOrFail($token));

        return $this->noIndex($this->trackResponse($asset, $track));
    }

    /**
     * Latest public Content, for the index and the home page.
     *
     * @return Builder<SpaceContent>
     */
    public static function publicQuery()
    {
        return SpaceContent::query()
            ->where('public_access', SpaceContent::ACCESS_PUBLIC)
            ->where('status', 'published')
            ->whereNotNull('active_revision_id')
            ->whereHas('author', fn ($author) => $author->where('status', 'active'));
    }

    /**
     * Card data for lists of public Content.
     *
     * @param  list<SpaceContent>  $contents
     * @return list<array{title: string, excerpt: string, url: string, cover: string|null, kind: string|null, author: Actor|null, published_at: CarbonInterface|null}>
     */
    public static function cards(array $contents, GuestContentAccess $access): array
    {
        $cards = [];
        foreach ($contents as $content) {
            $revision = $content->activeRevision;
            if (! $revision instanceof SpaceContentRevision) {
                continue;
            }
            $cover = ContentExcerpt::cover($revision);
            $cards[] = [
                'title' => (string) $revision->title,
                'excerpt' => ContentExcerpt::text($revision),
                'url' => $access->url($content, null),
                'cover' => $cover instanceof Asset ? $access->assetUrl($content, $cover, null) : null,
                'kind' => $content->blueprintVersion->blueprint->name ?? null,
                'author' => $content->author,
                'published_at' => $content->published_at,
            ];
        }

        return $cards;
    }

    private function reader(SpaceContent $content, ?ContentInvitation $invitation): View
    {
        $content->loadMissing(['author.user', 'author.profile', 'activeRevision.assets', 'blueprintVersion.blueprint', 'definition', 'context']);
        $revision = $content->activeRevision;
        abort_unless($revision instanceof SpaceContentRevision, 404);

        $definitionVersion = $revision->definitionVersion()->firstOrFail();
        $blocks = $revision->composition_mode === SpaceContentRevision::COMPOSITION_BLOCKS
            ? $revision->blocks()->get()
            : collect();

        $access = $this->access;
        $mediaExtras = MediaPresentation::forAssets(
            $revision->assets,
            $content->context,
            $content,
            fn (Asset $asset, AssetMediaVariant $variant): string => $access->variantUrl($content, $asset, $variant, $invitation),
            fn (Asset $asset, AssetMediaTrack $track): string => $access->trackUrl($content, $asset, $track, $invitation),
        );

        if ($invitation instanceof ContentInvitation) {
            $invitation->loadMissing('creator.user', 'creator.profile');
        }

        $cover = ContentExcerpt::cover($revision);

        return view('public-content.show', array_merge(ContentReaderStyle::variables($revision), [
            'content' => $content,
            'revision' => $revision,
            'definitionVersion' => $definitionVersion,
            'blocks' => $blocks,
            'mediaByPlacement' => $revision->assets->keyBy('pivot.uuid'),
            'mediaExtras' => $mediaExtras,
            'assetUrlFor' => fn (Asset $asset): string => $access->assetUrl($content, $asset, $invitation),
            'downloadUrlFor' => fn (Asset $asset): string => $access->assetUrl($content, $asset, $invitation, true),
            'canAnnotate' => false,
            'blockMarkerUuids' => [],
            'fieldMarkerUuids' => [],
            'assetMarkerUuids' => [],
            'kindLabel' => $content->blueprintVersion->blueprint->name ?? $content->definition->name,
            'children' => $access->readableChildren($content, $invitation),
            'navigation' => $access->navigation($content, $invitation),
            'invitation' => $invitation,
            'isInvitationRoot' => $invitation instanceof ContentInvitation && (int) $invitation->space_content_id === (int) $content->id,
            'excerpt' => ContentExcerpt::text($revision, 160),
            'coverUrl' => $cover instanceof Asset ? $access->assetUrl($content, $cover, $invitation) : null,
            'canonicalUrl' => $invitation === null ? $access->url($content, null) : null,
            'alsoPublicUrl' => $invitation !== null && $access->isPublic($content) ? $access->url($content, null) : null,
        ]));
    }

    private function invitationOrFail(string $token, bool $allowUnusable = false): ContentInvitation
    {
        $invitation = ContentInvitation::findByToken($token);
        abort_unless($invitation instanceof ContentInvitation, 404);
        abort_unless($allowUnusable || $invitation->isUsable(), 404);

        return $invitation;
    }

    private function authorizeAsset(SpaceContent $content, Asset $asset, ?ContentInvitation $invitation): void
    {
        abort_unless($this->access->allows($content, $invitation), 404);
        abort_unless($this->access->assetInEdition($content, $asset), 404);
        abort_unless(Storage::disk($asset->disk)->exists($asset->storage_key), 404);
    }

    private function variantResponse(Asset $asset, AssetMediaVariant $variant): Response
    {
        abort_unless(
            (int) $variant->asset_id === (int) $asset->id
                && $variant->type === AssetMediaVariant::TYPE_POSTER
                && $variant->storage_key !== null
                && Storage::disk((string) $variant->disk)->exists($variant->storage_key),
            404,
        );

        return Storage::disk((string) $variant->disk)->response($variant->storage_key, null, [
            'Content-Type' => (string) $variant->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function trackResponse(Asset $asset, AssetMediaTrack $track): Response
    {
        abort_unless((int) $track->asset_id === (int) $asset->id && Storage::disk($track->disk)->exists($track->storage_key), 404);

        return Storage::disk($track->disk)->response($track->storage_key, null, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function recordView(ContentInvitation $invitation): void
    {
        $now = now();
        DB::table('content_invitations')->where('id', $invitation->id)->update([
            'view_count' => DB::raw('view_count + 1'),
            'first_viewed_at' => $invitation->first_viewed_at ?? $now,
            'last_viewed_at' => $now,
        ]);
    }

    /** Invitation pages are personal: never indexed, and the token never leaks through the Referer header. */
    private function invitationHeaders(): array
    {
        return [
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ];
    }

    private function noIndex(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
