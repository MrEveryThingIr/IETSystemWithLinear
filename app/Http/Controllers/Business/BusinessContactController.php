<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\ActorAddress;
use App\Models\Business;
use App\Models\ContactPoint;
use App\Services\Contacts\ContactDirectoryService;
use App\Support\BusinessAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessContactController extends Controller
{
    public function storeContact(
        Request $request,
        Business $business,
        ContactDirectoryService $directory
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $directory->createContactPoint($business, $request->validate([
            'kind' => ['required', Rule::in(ContactDirectoryService::CONTACT_KINDS)],
            'label' => ['nullable', 'string', 'max:80'],
            'value' => ['required', 'string', 'max:500'],
            'visibility' => ['required', Rule::in(ContactDirectoryService::VISIBILITIES)],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]));

        return back()->with('status', __('business.messages.contact_added'));
    }

    public function destroyContact(
        Request $request,
        Business $business,
        ContactPoint $contactPoint,
        ContactDirectoryService $directory
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $directory->deleteContactPoint($business, $contactPoint);

        return back()->with('status', __('business.messages.contact_deleted'));
    }

    public function storeAddress(
        Request $request,
        Business $business,
        ContactDirectoryService $directory
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $directory->createAddress($business, $request->validate([
            'type' => ['required', Rule::in(ContactDirectoryService::ADDRESS_TYPES)],
            'label' => ['nullable', 'string', 'max:100'],
            'country_code' => ['required', 'string', 'size:2'],
            'province' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:160'],
            'street' => ['nullable', 'string', 'max:255'],
            'alley' => ['nullable', 'string', 'max:160'],
            'building_no' => ['nullable', 'string', 'max:60'],
            'unit' => ['nullable', 'string', 'max:60'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'visibility' => ['required', Rule::in(ContactDirectoryService::VISIBILITIES)],
            'is_primary' => ['nullable', 'boolean'],
        ]));

        return back()->with('status', __('business.messages.address_added'));
    }

    public function destroyAddress(
        Request $request,
        Business $business,
        ActorAddress $address,
        ContactDirectoryService $directory
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $directory->deleteAddress($business, $address);

        return back()->with('status', __('business.messages.address_deleted'));
    }
}
