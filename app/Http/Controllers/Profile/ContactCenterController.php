<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Actor;
use App\Models\ActorAddress;
use App\Models\ContactPoint;
use App\Services\Contacts\ContactDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactCenterController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $actor = $this->actor($request);

        $contactPoints = ContactPoint::query()
            ->where('contactable_type', $actor->getMorphClass())
            ->where('contactable_id', $actor->getKey())
            ->orderByDesc('is_primary')
            ->orderBy('kind')
            ->orderBy('id')
            ->get();

        $addresses = ActorAddress::query()
            ->where('addressable_type', $actor->getMorphClass())
            ->where('addressable_id', $actor->getKey())
            ->orderByDesc('is_primary')
            ->orderBy('type')
            ->orderBy('id')
            ->get();

        return view('profile.contact-center.index', compact(
            'user',
            'contactPoints',
            'addresses',
        ));
    }

    public function storeContact(
        Request $request,
        ContactDirectoryService $directory
    ): RedirectResponse {
        $data = $request->validate($this->contactRules());

        $directory->createContactPoint($this->actor($request), $data);

        return back()->with('status', 'راه ارتباطی با موفقیت اضافه شد.');
    }

    public function updateContact(
        Request $request,
        ContactPoint $contactPoint,
        ContactDirectoryService $directory
    ): RedirectResponse {
        $data = $request->validate($this->contactRules());

        $directory->updateContactPoint($this->actor($request), $contactPoint, $data);

        return back()->with('status', 'راه ارتباطی به‌روزرسانی شد.');
    }

    public function destroyContact(
        Request $request,
        ContactPoint $contactPoint,
        ContactDirectoryService $directory
    ): RedirectResponse {
        $directory->deleteContactPoint($this->actor($request), $contactPoint);

        return back()->with('status', 'راه ارتباطی حذف شد.');
    }

    public function storeAddress(
        Request $request,
        ContactDirectoryService $directory
    ): RedirectResponse {
        $data = $request->validate($this->addressRules());

        $directory->createAddress($this->actor($request), $data);

        return back()->with('status', 'آدرس با موفقیت اضافه شد.');
    }

    public function updateAddress(
        Request $request,
        ActorAddress $address,
        ContactDirectoryService $directory
    ): RedirectResponse {
        $data = $request->validate($this->addressRules());

        $directory->updateAddress($this->actor($request), $address, $data);

        return back()->with('status', 'آدرس به‌روزرسانی شد.');
    }

    public function destroyAddress(
        Request $request,
        ActorAddress $address,
        ContactDirectoryService $directory
    ): RedirectResponse {
        $directory->deleteAddress($this->actor($request), $address);

        return back()->with('status', 'آدرس حذف شد.');
    }

    private function contactRules(): array
    {
        return [
            'kind' => ['required', Rule::in(ContactDirectoryService::CONTACT_KINDS)],
            'label' => ['nullable', 'string', 'max:80'],
            'value' => ['required', 'string', 'max:500'],
            'visibility' => ['required', Rule::in(ContactDirectoryService::VISIBILITIES)],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function addressRules(): array
    {
        return [
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'visibility' => ['required', Rule::in(ContactDirectoryService::VISIBILITIES)],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function actor(Request $request): Actor
    {
        $actor = $request->user()?->actor;
        abort_unless($actor instanceof Actor && $actor->status === 'active', 403);

        return $actor;
    }
}
