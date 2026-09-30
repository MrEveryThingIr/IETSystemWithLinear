<?php

namespace App\Console\Commands;

use App\Models\PublicIntakePortal;
use Illuminate\Console\Command;

class CreatePublicRealEstatePortal extends Command
{
    protected $signature = 'real-estate:intake-portal
                            {--title=دفتر املاک : Public title}
                            {--welcome=اطلاعات ملک یا درخواست خود را ثبت کنید؛ پس از ثبت، دفتر با شما تماس می‌گیرد. : Welcome text}
                            {--locale=fa : Portal locale}';

    protected $description = 'Create an opaque public real-estate intake portal suitable for a QR code';

    public function handle(): int
    {
        $portal = PublicIntakePortal::query()->create([
            'type' => 'real_estate',
            'title' => (string) $this->option('title'),
            'welcome_heading' => 'ثبت ملک و درخواست',
            'welcome_body' => (string) $this->option('welcome'),
            'success_message' => 'اطلاعات شما با موفقیت ثبت شد. این پیش‌نمایش فقط همین یک بار نمایش داده می‌شود.',
            'locale' => (string) $this->option('locale'),
            'is_active' => true,
        ]);

        $this->newLine();
        $this->info('Public intake portal created.');
        $this->line('Title: '.$portal->title);
        $this->line('URL: '.route('public.real-estate.show', $portal));
        $this->line('Token: '.$portal->public_token);
        $this->newLine();
        $this->warn('Keep the URL unlisted. Put this URL behind the office QR code.');

        return self::SUCCESS;
    }
}
