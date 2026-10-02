<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ContextualWorkflowShellTest extends TestCase
{
    public function test_c3_primary_workflows_use_the_shared_page_contract(): void
    {
        $views = [
            'livewire/relationships/show.blade.php',
            'businesses/show.blade.php',
            'livewire/groups/show.blade.php',
            'livewire/contexts/content-studio.blade.php',
        ];

        foreach ($views as $view) {
            $contents = File::get(resource_path('views/'.$view));

            $this->assertStringContainsString(
                '<x-app.workflow-shell',
                $contents,
                $view.' must use the C3 shared workflow shell.',
            );
        }
    }

    public function test_shared_shell_exposes_every_standard_page_contract_field(): void
    {
        $contents = File::get(resource_path('views/components/app/workflow-shell.blade.php'));

        foreach ([
            "workflow.purpose",
            "workflow.current_state",
            "workflow.next_action",
            "workflow.audience",
            "workflow.durable_result",
            "workflow.consequence",
            "workflow.contextual_help",
            "workflow.advanced",
        ] as $key) {
            $this->assertStringContainsString($key, $contents);
        }
    }

    public function test_deal_shell_uses_the_full_human_pipeline_without_replacing_kernel_stages(): void
    {
        $workflow = require lang_path('en/workflow.php');

        $this->assertSame(
            ['need_offer', 'match', 'deal', 'terms', 'agreement', 'work', 'review', 'settlement'],
            array_keys($workflow['deal']['steps']),
        );

        $pipeline = File::get(app_path('Support/DealPipeline.php'));

        $this->assertStringContainsString(
            "return ['connect', 'negotiate', 'agree', 'work', 'review', 'settle'];",
            $pipeline,
            'C3 must compose the DealPipeline instead of rewriting its authoritative stage contract.',
        );
    }

    public function test_group_governance_and_content_power_tools_are_progressively_disclosed(): void
    {
        $group = File::get(resource_path('views/livewire/groups/show.blade.php'));
        $content = File::get(resource_path('views/livewire/contexts/content-studio.blade.php'));

        $this->assertStringContainsString('id="group-manage"', $group);
        $this->assertStringContainsString('<details id="group-manage"', $group);
        $this->assertStringContainsString('<x-slot:advanced>', $content);
        $this->assertStringContainsString("route('contexts.contents.ai'", $content);
        $this->assertStringContainsString("route('contexts.contents.blocks'", $content);
        $this->assertStringContainsString("route('contexts.contents.appearance'", $content);
    }

    public function test_c3_workflow_copy_has_locale_parity(): void
    {
        $english = Arr::dot(require lang_path('en/workflow.php'));

        foreach (['fa', 'ar', 'zh_CN'] as $locale) {
            $localized = Arr::dot(require lang_path($locale.'/workflow.php'));

            foreach (array_keys($english) as $key) {
                $this->assertArrayHasKey($key, $localized, $locale.' is missing workflow key '.$key);
            }
        }
    }
}
