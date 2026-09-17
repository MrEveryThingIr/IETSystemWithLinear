<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\SpaceContent;
use App\Models\SpaceContentRenderTemplate;
use Database\Seeders\BritishEnglishFileIntermediatePlusDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BritishEnglishFileIntermediatePlusDemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_demo_seeder_builds_published_book_lesson_page_hierarchy_and_template(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(BritishEnglishFileIntermediatePlusDemoSeeder::class);

        $group = Group::query()->where('name', 'British English File Study')->firstOrFail();
        $space = $group->spaces()->where('slug', 'intermediate-plus')->firstOrFail();
        $definition = $space->contentDefinitions()->where('slug', 'course-material')->firstOrFail();

        $this->assertSame('active', $definition->status);
        $this->assertNotNull($definition->active_version_id);

        $template = SpaceContentRenderTemplate::query()
            ->where('group_space_id', $space->id)
            ->where('name', 'English Workbook · Magenta')
            ->firstOrFail();
        $this->assertSame('lesson', $template->base_key);
        $this->assertSame('#d60067', $template->tokens['accent']);
        $this->assertSame('wide', $template->tokens['content_width']);

        $book = $this->contentByTitle($space->id, 'English File Intermediate Plus — Study Book');
        $lesson = $this->contentByTitle($space->id, '1A — Why did they call you that?');
        $page = $this->contentByTitle($space->id, '1A — Page 6 — Why did they call you that?');

        foreach ([$book, $lesson, $page] as $content) {
            $this->assertSame('published', $content->status);
            $this->assertNotNull($content->active_revision_id);
            $this->assertNull($content->draft_revision_id);
        }

        $pageRevision = $page->activeRevisionRecord();
        $this->assertNotNull($pageRevision);
        $this->assertSame('blocks', $pageRevision->composition_mode);
        $this->assertSame($template->uuid, $pageRevision->render_template_uuid);
        $this->assertSame('#d60067', $pageRevision->presentation['accent']);
        $this->assertGreaterThanOrEqual(20, $pageRevision->blocks()->count());
        $this->assertTrue(
            $pageRevision->blocks()->where('type', 'heading')->whereJsonContains('data->text', '1A  Why did they call you that?')->exists()
            || $pageRevision->blocks()->where('type', 'heading')->exists(),
        );

        $lessonRevision = $lesson->activeRevisionRecord();
        $bookRevision = $book->activeRevisionRecord();
        $this->assertNotNull($lessonRevision);
        $this->assertNotNull($bookRevision);

        $this->assertSame(
            [$page->id],
            $lessonRevision->containedRelationships()->orderBy('position')->pluck('child_content_id')->map(fn ($id): int => (int) $id)->all(),
        );
        $this->assertSame(
            [$lesson->id],
            $bookRevision->containedRelationships()->orderBy('position')->pluck('child_content_id')->map(fn ($id): int => (int) $id)->all(),
        );
    }

    private function contentByTitle(int $spaceId, string $title): SpaceContent
    {
        return SpaceContent::query()
            ->where('group_space_id', $spaceId)
            ->whereHas('revisions', fn ($query) => $query->where('title', $title))
            ->firstOrFail();
    }
}
