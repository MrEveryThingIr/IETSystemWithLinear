<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Context;
use App\Models\MediaUploadSession;
use App\Models\SpaceContent;
use App\Models\User;
use App\Support\Media\ResumableUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * JSON endpoints for resumable audio/video uploads into a Content draft.
 *
 * create → upload chunks (each below the host PHP limit) → complete.
 * The browser can ask for the session status at any time to resume after a
 * dropped connection or a page refresh.
 */
class MediaUploadSessionController extends Controller
{
    public function __construct(private readonly ResumableUploads $uploads) {}

    public function store(Request $request, Context $context, SpaceContent $content): JsonResponse
    {
        abort_unless((int) $content->context_id === (int) $context->id, 404);

        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'byte_size' => ['required', 'integer', 'min:1'],
            'mime_type' => ['required', 'string', 'max:120'],
            'rights_status' => ['required', 'string', Rule::in(Asset::RIGHTS_STATUSES)],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $session = $this->uploads->create(
            $content,
            $this->user($request),
            $data['filename'],
            (int) $data['byte_size'],
            $data['mime_type'],
            $data['rights_status'],
            $data['caption'] ?? null,
        );

        return response()->json($this->state($session), 201);
    }

    public function show(Request $request, MediaUploadSession $session): JsonResponse
    {
        $this->uploads->authorize($session, $this->user($request));

        return response()->json($this->state($session->fresh() ?? $session));
    }

    public function chunk(Request $request, MediaUploadSession $session): JsonResponse
    {
        $offset = filter_var($request->query('offset'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        abort_if($offset === false, 422, 'A non-negative offset is required.');

        $stream = $request->getContent(true);
        abort_unless(is_resource($stream), 422, 'The chunk body could not be read.');

        try {
            $received = $this->uploads->append($session, $this->user($request), (int) $offset, $stream);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            // Out of order or already closed: tell the browser where to continue.
            return response()->json($this->state($session->fresh() ?? $session), 409);
        }

        return response()->json(['received_bytes' => $received]);
    }

    public function complete(Request $request, MediaUploadSession $session): JsonResponse
    {
        $data = $request->validate([
            'duration_ms' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'integer', 'min:0'],
            'height' => ['nullable', 'integer', 'min:0'],
            'poster' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
        ]);

        $poster = $request->file('poster');
        $asset = $this->uploads->complete(
            $session,
            $this->user($request),
            [
                'duration_ms' => $data['duration_ms'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
            ],
            $poster instanceof UploadedFile ? $poster : null,
        );

        return response()->json([
            ...$this->state($session->fresh() ?? $session),
            'asset_uuid' => $asset->uuid,
        ]);
    }

    public function destroy(Request $request, MediaUploadSession $session): JsonResponse
    {
        $this->uploads->cancel($session, $this->user($request));

        return response()->json($this->state($session->fresh() ?? $session));
    }

    /** @return array<string, mixed> */
    private function state(MediaUploadSession $session): array
    {
        return [
            'uuid' => $session->uuid,
            'status' => $session->status,
            'byte_size' => $session->byte_size,
            'received_bytes' => $session->received_bytes,
            'chunk_size' => $session->chunk_size,
            'error' => $session->error,
            'urls' => [
                'status' => route('contexts.contents.uploads.show', $session),
                'chunk' => route('contexts.contents.uploads.chunk', $session),
                'complete' => route('contexts.contents.uploads.complete', $session),
                'cancel' => route('contexts.contents.uploads.destroy', $session),
            ],
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
