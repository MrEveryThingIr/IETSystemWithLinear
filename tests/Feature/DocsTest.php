<?php

namespace Tests\Feature;

use App\Models\Actor;
use App\Models\DocContribution;
use App\Models\DocInvitation;
use App\Models\SpaceContent;
use App\Support\Docs\DocsCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The public documentation: repository-owned, bilingual, separate from Content, with an expert intake. */
class DocsTest extends TestCase
{
    use RefreshDatabase;

    private function proposal(array $overrides = []): array
    {
        return [
            'topic' => 'barter',
            'name' => 'Sara Rahimi',
            'email' => 'Sara@Example.com',
            'field' => 'accounting',
            'expertise' => 'Ten years of bookkeeping for small construction firms.',
            'education' => 'BA accounting, self-taught in cooperatives',
            'proposal' => 'Value in-kind settlements at the date of delivery against a published basket, and keep the basket in the agreement.',
            'agree' => '1',
            ...$overrides,
        ];
    }

    public function test_guests_read_the_index_and_every_chapter_without_an_account(): void
    {
        $catalog = app(DocsCatalog::class);
        $this->get(route('docs.index'))->assertOk()->assertSeeHtml('data-docs-index')->assertSee('Understand the system');

        foreach ($catalog->pages('en') as $page) {
            $this->get(route('docs.show', $page['slug']))->assertOk()->assertSee($page['title'], false)->assertSeeHtml('data-docs-body');
        }

        $this->get(route('docs.show', 'no-such-chapter'))->assertNotFound();
    }

    public function test_every_english_chapter_has_a_persian_edition_that_renders_right_to_left(): void
    {
        $catalog = app(DocsCatalog::class);
        $this->assertSame($catalog->slugs(), array_column($catalog->pages('fa'), 'slug'), 'same chapters in the same order');

        $this->withSession(['locale' => 'fa'])->get(route('docs.index'))->assertOk()->assertSee('dir="rtl"', false)->assertSee('سامانه را بشناسید');
        $this->withSession(['locale' => 'fa'])->get(route('docs.show', 'barter'))->assertOk()->assertSee('تهاتر', false)->assertDontSeeHtml('data-docs-fallback');
    }

    public function test_a_language_without_an_edition_reads_the_english_one_with_a_notice(): void
    {
        $this->withSession(['locale' => 'ar'])->get(route('docs.show', 'welcome'))->assertOk()->assertSeeHtml('data-docs-fallback')->assertSee('Welcome');
    }

    public function test_chapter_headers_and_links_are_consistent_in_every_language(): void
    {
        $catalog = app(DocsCatalog::class);
        $fields = config('docs.fields');
        $slugs = $catalog->slugs();

        foreach (['en', 'fa'] as $locale) {
            foreach ($catalog->pages($locale) as $page) {
                $this->assertNotSame('', $page['title'], "$locale/{$page['slug']} title");
                $this->assertNotSame('', $page['summary'], "$locale/{$page['slug']} summary");
                foreach ($page['experts'] as $field) {
                    $this->assertContains($field, $fields, "$locale/{$page['slug']} names an unknown field $field");
                }
                $html = $catalog->page($page['slug'], $locale)['html'];
                preg_match_all('#href="/docs/([a-z0-9-]+)"#', $html, $links);
                foreach ($links[1] as $target) {
                    $this->assertTrue($target === 'collaborate' || in_array($target, $slugs, true), "$locale/{$page['slug']} links to a missing page: $target");
                }
            }
        }

        foreach (['en', 'fa'] as $locale) {
            $strings = require lang_path("$locale/docs.php");
            foreach ($fields as $field) {
                $this->assertArrayHasKey($field, $strings['fields'], "$locale label for $field");
            }
        }
    }

    public function test_docs_are_not_content_and_never_appear_in_the_public_library(): void
    {
        config()->set('public_content.index', true);

        $this->get(route('public-content.index'))->assertOk()->assertDontSee('Getting started');
        $this->get(route('docs.show', 'start'))->assertOk();
        $this->assertSame(0, SpaceContent::query()->count(), 'documentation creates no Content');
    }

    public function test_a_chapter_invites_the_relevant_experts_to_collaborate(): void
    {
        $this->get(route('docs.show', 'barter'))->assertOk()->assertSeeHtml('data-docs-invite')->assertSee('Economics')
            ->assertSee(e(route('docs.contribute', ['topic' => 'barter', 'field' => 'economics'])), false);
    }

    public function test_invite_only_mode_needs_a_signed_in_user_or_a_valid_link(): void
    {
        config()->set('docs.access', 'invite');
        $token = str_repeat('a', 48);
        DocInvitation::query()->create(['label' => 'Economists', 'token_hash' => DocInvitation::hashToken($token), 'expires_at' => now()->addDay(), 'max_uses' => 2]);

        $this->get(route('docs.index'))->assertForbidden()->assertSeeHtml('data-docs-restricted');
        $this->get(route('docs.show', 'welcome'))->assertForbidden();
        $this->post(route('docs.contribute.submit'), $this->proposal())->assertForbidden();

        $this->get(route('docs.join', $token))->assertRedirect(route('docs.index'));
        $this->get(route('docs.index'))->assertOk()->assertSee('noindex', false);
        $this->assertSame(1, DocInvitation::query()->sole()->uses_count);

        // A signed-in person never needs a link.
        $this->flushSession();
        $this->actingAs(Actor::factory()->create()->user)->get(route('docs.index'))->assertOk();
    }

    public function test_revoked_expired_and_exhausted_links_do_not_open_the_docs(): void
    {
        config()->set('docs.access', 'invite');
        $make = fn (string $c, array $a): string => tap(str_repeat($c, 48), fn (string $t) => DocInvitation::query()->create(['label' => $c, 'token_hash' => DocInvitation::hashToken($t), ...$a]));

        $revoked = $make('r', ['revoked_at' => now()]);
        $expired = $make('e', ['expires_at' => now()->subMinute()]);
        $used = $make('u', ['max_uses' => 1, 'uses_count' => 1]);

        foreach ([$revoked, $expired, $used, str_repeat('z', 48)] as $token) {
            $this->get(route('docs.join', $token))->assertForbidden();
        }
        $this->get(route('docs.index'))->assertForbidden();
    }

    public function test_the_console_creates_a_link_shown_once_and_stores_only_its_hash(): void
    {
        $this->artisan('docs:invite', ['label' => 'Cooperative economists', '--days' => 7, '--uses' => 5])->assertSuccessful();

        $invitation = DocInvitation::query()->sole();
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertSame(5, $invitation->max_uses);
        $this->assertTrue($invitation->isUsable());

        $this->artisan('docs:invite', ['--revoke' => $invitation->id])->assertSuccessful();
        $this->assertFalse($invitation->fresh()->isUsable());
    }

    public function test_a_guest_can_send_a_proposal_with_expertise_and_education(): void
    {
        $this->get(route('docs.contribute', ['topic' => 'barter', 'field' => 'accounting']))->assertOk()->assertSeeHtml('data-docs-form')
            ->assertSee('What is your expertise?');

        $this->post(route('docs.contribute.submit'), $this->proposal())->assertRedirect(route('docs.contribute'))->assertSessionHas('docs.thanks');

        $saved = DocContribution::query()->sole();
        $this->assertNull($saved->user_id);
        $this->assertSame('sara@example.com', $saved->email);
        $this->assertSame('barter', $saved->topic);
        $this->assertSame('accounting', $saved->field);
        $this->assertSame('new', $saved->status);
        $this->assertFalse($saved->may_credit);
    }

    public function test_a_signed_in_member_is_linked_but_nothing_is_copied_from_their_profile(): void
    {
        $user = Actor::factory()->create()->user;

        $this->actingAs($user)->post(route('docs.contribute.submit'), $this->proposal(['field' => 'other', 'field_other' => 'Mutual credit', 'may_credit' => '1']))
            ->assertSessionHasNoErrors();

        $saved = DocContribution::query()->sole();
        $this->assertSame($user->id, $saved->user_id);
        $this->assertSame('other: Mutual credit', $saved->field);
        $this->assertTrue($saved->may_credit);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function badProposals(): array
    {
        return [
            'no expertise' => [['expertise' => '']],
            'no education' => [['education' => '']],
            'too short' => [['proposal' => 'too short']],
            'bad email' => [['email' => 'not-an-email']],
            'unknown field' => [['field' => 'astrology']],
            'unknown chapter' => [['topic' => 'not-a-chapter']],
            'not agreed' => [['agree' => '']],
            'other without a name' => [['field' => 'other', 'field_other' => '']],
        ];
    }

    #[DataProvider('badProposals')]
    public function test_incomplete_or_malformed_proposals_are_rejected(array $bad): void
    {
        $this->post(route('docs.contribute.submit'), $this->proposal($bad))->assertSessionHasErrors();

        $this->assertSame(0, DocContribution::query()->count());
    }

    public function test_the_hidden_trap_field_stores_nothing(): void
    {
        $this->post(route('docs.contribute.submit'), $this->proposal(['website' => 'http://spam.example']))->assertRedirect();
        $this->assertSame(0, DocContribution::query()->count());
    }

    public function test_the_console_lists_reads_and_marks_proposals(): void
    {
        $this->post(route('docs.contribute.submit'), $this->proposal());
        $id = DocContribution::query()->sole()->id;

        $this->artisan('docs:contributions')->expectsOutputToContain('Sara Rahimi')->assertSuccessful();
        $this->artisan('docs:contributions', ['--show' => $id])->expectsOutputToContain('bookkeeping')->assertSuccessful();
        $this->artisan('docs:contributions', ['--mark' => $id, '--as' => 'accepted'])->assertSuccessful();
        $this->assertSame('accepted', DocContribution::query()->sole()->status);
        $this->artisan('docs:contributions', ['--mark' => $id, '--as' => 'bogus'])->assertFailed();
    }

    public function test_the_help_button_in_the_app_opens_the_matching_chapter(): void
    {
        $actor = Actor::factory()->create();

        $this->withoutVite()->actingAs($actor->user)->get(route('dashboard'))->assertOk()->assertSee(route('docs.show', 'start'), false);
    }
}
