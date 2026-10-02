<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessContact;
use App\Services\Business\BusinessMarketService;
use App\Services\Contacts\BusinessContactResolver;
use App\Services\Contacts\ContactDirectoryService;
use App\Support\BusinessAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BusinessClientController extends Controller
{
    public function index(Request $request, Business $business): View
    {
        abort_unless(BusinessAccess::canOperate($request->user(), $business), 403);

        $query = $business->businessContacts()
            ->with(['contactPoints', 'addresses'])
            ->orderByRaw("case status when 'active' then 1 else 2 end")
            ->latest();

        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));

            $query->where(function ($contacts) use ($term): void {
                $contacts->where('display_name', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%")
                    ->orWhere('source', 'like', "%{$term}%")
                    ->orWhereHas('contactPoints', fn ($points) => $points
                        ->where('value', 'like', "%{$term}%")
                        ->orWhere('normalized_value', 'like', "%{$term}%"));
            });
        }

        return view('businesses.clients.index', [
            'business' => $business,
            'clients' => $query->paginate(30)->withQueryString(),
            'canManage' => BusinessAccess::canManage($request->user(), $business),
        ]);
    }

    public function store(
        Request $request,
        Business $business,
        BusinessContactResolver $resolver,
        ContactDirectoryService $directory,
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:64'],
            'secondary_phone' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'city' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:160'],
            'street' => ['nullable', 'string', 'max:255'],
        ]);

        $contact = $resolver->resolve(
            $business,
            $data['display_name'],
            $data['phone'],
            filled($data['source'] ?? null) ? trim((string) $data['source']) : 'manual',
        );

        $contact->update([
            'display_name' => trim($data['display_name']),
            'source' => filled($data['source'] ?? null) ? trim((string) $data['source']) : $contact->source,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : $contact->notes,
            'status' => 'active',
        ]);

        if (filled($data['secondary_phone'] ?? null)) {
            $normalized = $directory->normalize('mobile', (string) $data['secondary_phone']);

            if (! $contact->contactPoints()->where('normalized_value', $normalized)->exists()) {
                $directory->createContactPoint($contact, [
                    'kind' => 'mobile',
                    'label' => 'Secondary phone',
                    'value' => $data['secondary_phone'],
                    'visibility' => 'private',
                    'is_primary' => false,
                ]);
            }
        }

        if (filled($data['city'] ?? null) || filled($data['district'] ?? null) || filled($data['street'] ?? null)) {
            $directory->createAddress($contact, [
                'type' => 'other',
                'label' => 'Client address',
                'country_code' => 'IR',
                'city' => $data['city'] ?? null,
                'district' => $data['district'] ?? null,
                'street' => $data['street'] ?? null,
                'visibility' => 'private',
            ]);
        }

        return back()->with('status', 'Client/contact saved.');
    }

    public function publishNeed(
        Request $request,
        Business $business,
        BusinessContact $client,
        BusinessMarketService $market,
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $data = $request->validate([
            'concept_label' => ['required', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'subject_kind' => ['required', Rule::in(['property', 'good', 'service', 'capital', 'collaboration', 'other'])],
            'arrangement_kind' => ['required', Rule::in(['ownership_transfer', 'temporary_use', 'service', 'financing', 'collaboration', 'other'])],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:64'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'cash_min' => ['nullable', 'numeric', 'min:0'],
            'cash_max' => ['nullable', 'numeric', 'min:0', 'gte:cash_min'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'cash_basis' => ['nullable', Rule::in(['total', 'hour', 'day', 'week', 'month', 'year'])],
        ]);

        $intent = $market->publishClientNeed(
            $business,
            $client,
            $request->user(),
            $data['concept_label'],
            $data,
        );

        return redirect()
            ->route('intents.matches', $intent)
            ->with('status', __('business_listing.messages.client_need_published'));
    }

    public function archive(
        Request $request,
        Business $business,
        BusinessContact $client,
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);
        abort_unless(
            $client->owner_type === $business->getMorphClass()
            && (int) $client->owner_id === (int) $business->id,
            404,
        );

        $client->update(['status' => 'inactive']);

        return back()->with('status', 'Client/contact archived.');
    }
}
