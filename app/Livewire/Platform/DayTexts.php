<?php

namespace App\Livewire\Platform;

use App\Models\DayText;
use App\Models\User;
use App\Support\Localization;
use App\Support\PlatformAdmin;
use App\Support\TimeBar\DayTexts as DayTextChooser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** The superadmin writes the "text of the day" shown in the top bar of every page. */
#[Layout('layouts.app')]
class DayTexts extends Component
{
    public bool $showDefault = true;

    public bool $formOpen = false;

    public ?string $editing = null;

    public string $kind = 'quote';

    /** @var array<string, string> */
    public array $texts = [];

    public string $attribution = '';

    public string $link = '';

    public ?string $startsOn = null;

    public ?string $endsOn = null;

    public bool $active = true;

    public string $status = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->showDefault = DayTextChooser::showsDefault();
        $this->texts = array_fill_keys(Localization::codes(), '');
    }

    public function updatedShowDefault(bool $value): void
    {
        $this->authorizeAdmin();
        DayTextChooser::setShowDefault($value);
        $this->status = __('time_bar.admin.saved');
    }

    public function create(): void
    {
        $this->authorizeAdmin();
        $this->resetForm();
        $this->formOpen = true;
    }

    public function edit(string $uuid): void
    {
        $this->authorizeAdmin();
        $text = $this->find($uuid);
        $this->resetForm();
        $this->editing = $text->uuid;
        $this->kind = $text->kind;
        foreach (Localization::codes() as $code) {
            $this->texts[$code] = (string) ($text->texts[$code] ?? '');
        }
        $this->attribution = (string) $text->attribution;
        $this->link = (string) $text->link_url;
        $this->startsOn = $text->starts_on?->toDateString();
        $this->endsOn = $text->ends_on?->toDateString();
        $this->active = $text->active;
        $this->formOpen = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorizeAdmin();
        $this->startsOn = $this->startsOn ?: null;
        $this->endsOn = $this->endsOn ?: null;
        $this->validate([
            'kind' => ['required', 'in:'.implode(',', DayText::KINDS)],
            'texts' => ['array'],
            'texts.*' => ['nullable', 'string', 'max:500'],
            'attribution' => ['nullable', 'string', 'max:160'],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            'startsOn' => ['nullable', 'date_format:Y-m-d'],
            'endsOn' => ['nullable', 'date_format:Y-m-d'],
        ], [], [
            'link' => __('time_bar.admin.fields.link'),
            'attribution' => __('time_bar.admin.fields.attribution'),
        ]);

        $texts = [];
        foreach (Localization::codes() as $code) {
            $value = trim((string) ($this->texts[$code] ?? ''));
            if ($value !== '') {
                $texts[$code] = $value;
            }
        }
        if ($texts === []) {
            $this->addError('texts', __('time_bar.admin.errors.text_required'));

            return;
        }
        if ($this->startsOn !== null && $this->endsOn !== null && $this->endsOn < $this->startsOn) {
            $this->addError('endsOn', __('time_bar.admin.errors.range'));

            return;
        }

        $values = [
            'kind' => $this->kind,
            'texts' => $texts,
            'attribution' => trim($this->attribution) ?: null,
            'link_url' => trim($this->link) ?: null,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
            'active' => $this->active,
            'updated_by_user_id' => $this->user()->id,
        ];

        if ($this->editing !== null) {
            $this->find($this->editing)->update($values);
        } else {
            DayText::query()->create($values + [
                'created_by_user_id' => $this->user()->id,
                'sort_order' => ((int) DayText::query()->max('sort_order')) + 1,
            ]);
        }

        $this->resetForm();
        $this->status = __('time_bar.admin.saved');
    }

    public function toggle(string $uuid): void
    {
        $this->authorizeAdmin();
        $text = $this->find($uuid);
        $text->update(['active' => ! $text->active, 'updated_by_user_id' => $this->user()->id]);
    }

    public function move(string $uuid, int $direction): void
    {
        $this->authorizeAdmin();
        $ordered = DayText::query()->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $ordered->search(fn (DayText $text): bool => $text->uuid === $uuid);
        $target = $index === false ? null : $index + ($direction < 0 ? -1 : 1);
        if ($index === false || $target < 0 || $target >= $ordered->count()) {
            return;
        }

        $list = $ordered->all();
        [$list[$index], $list[$target]] = [$list[$target], $list[$index]];
        DB::transaction(function () use ($list): void {
            foreach ($list as $position => $text) {
                if ($text->sort_order !== $position) {
                    $text->update(['sort_order' => $position]);
                }
            }
        });
    }

    public function delete(string $uuid): void
    {
        $this->authorizeAdmin();
        $this->find($uuid)->delete();
        if ($this->editing === $uuid) {
            $this->resetForm();
        }
        $this->status = __('time_bar.admin.deleted');
    }

    public function render(): View
    {
        $this->authorizeAdmin();
        $locale = app()->getLocale();

        return view('livewire.platform.day-texts', [
            'entries' => DayText::query()->orderBy('sort_order')->orderBy('id')->get(),
            'today' => DayTextChooser::today($this->user(), $locale),
            'defaultText' => DayTextChooser::defaultText($locale),
            'languages' => Localization::supported(),
            'locale' => $locale,
        ])->title(__('time_bar.admin.title'));
    }

    private function resetForm(): void
    {
        $this->reset('formOpen', 'editing', 'kind', 'attribution', 'link', 'startsOn', 'endsOn', 'active');
        $this->texts = array_fill_keys(Localization::codes(), '');
        $this->resetValidation();
    }

    private function find(string $uuid): DayText
    {
        return DayText::query()->where('uuid', $uuid)->firstOrFail();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(PlatformAdmin::check($this->user()), 403);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
