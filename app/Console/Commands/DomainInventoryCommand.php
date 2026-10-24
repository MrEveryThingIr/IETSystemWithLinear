<?php

namespace App\Console\Commands;

use App\Support\Categories\LifeDomainCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only report of the records that the 1.8–1.12 redesign touches, so the
 * real production data can be reviewed before anything is mapped. It never
 * writes; run it before and after an upgrade and compare.
 */
class DomainInventoryCommand extends Command
{
    protected $signature = 'iet:inventory {--json : Print the report as JSON}';

    protected $description = 'Read-only report of skills, categories, needs/offers, work, conversations and bookkeeping records';

    public function handle(): int
    {
        $report = [
            'generated_at' => now()->toIso8601String(),
            'skills' => $this->skills(),
            'categories' => $this->categories(),
            'needs_offers' => $this->needsOffers(),
            'businesses' => $this->businesses(),
            'work' => $this->work(),
            'conversations' => $this->conversations(),
            'bookkeeping' => $this->bookkeeping(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($this->flatten($report) as $key => $value) {
            $this->line($key.': '.$value);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string|int, mixed>  $values
     * @return array<string, string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $lines = [];
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $lines += $value === [] ? [$path => '(none)'] : $this->flatten($value, $path);
            } else {
                $lines[$path] = is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;
            }
        }

        return $lines;
    }

    /** @return array<string, mixed> */
    private function skills(): array
    {
        if (! Schema::hasTable('professions')) {
            return ['professions' => 'table missing'];
        }

        $byProfession = [];
        foreach (DB::table('professions')->orderBy('id')->get(['id', 'code', 'name']) as $profession) {
            $byProfession[$profession->code] = [
                'name' => $profession->name,
                'people' => $this->countWhere('actor_professions', 'profession_id', $profession->id),
                'business_members' => $this->countWhere('business_membership_profession', 'profession_id', $profession->id),
                'maps_to' => LifeDomainCatalog::LEGACY_PROFESSIONS[$profession->code] ?? 'UNMAPPED — needs a decision',
            ];
        }

        return [
            'professions' => DB::table('professions')->count(),
            'people_skills' => $this->count('actor_professions'),
            'people_with_skills' => Schema::hasTable('actor_professions') ? DB::table('actor_professions')->distinct()->count('actor_id') : 0,
            'people_skills_by_level' => $this->grouped('actor_professions', 'level'),
            'people_skills_by_visibility' => $this->grouped('actor_professions', 'visibility'),
            'business_member_skills' => $this->count('business_membership_profession'),
            'capabilities_already_created' => $this->count('capabilities'),
            'by_profession' => $byProfession,
        ];
    }

    /** @return array<string, mixed> */
    private function categories(): array
    {
        return [
            'vocabularies_by_scope' => $this->grouped('concept_vocabularies', 'scope_type'),
            'concepts_by_status' => $this->grouped('concepts', 'status'),
            'concept_labels_by_locale' => $this->grouped('concept_labels', 'locale'),
            'schemes' => $this->count('concept_schemes'),
            'hierarchy_edges' => $this->count('concept_hierarchy_edges'),
            'concept_assertions' => $this->count('concept_assertions'),
            'shared_category_tree_installed' => Schema::hasTable('concept_vocabularies')
                && DB::table('concept_vocabularies')->where('scope_type', 'platform')->where('slug', LifeDomainCatalog::VOCABULARY_SLUG)->exists(),
            'suggestions_by_status' => $this->grouped('concept_suggestions', 'status'),
        ];
    }

    /** @return array<string, mixed> */
    private function needsOffers(): array
    {
        $table = 'actor_profile_intents';
        if (! Schema::hasTable($table)) {
            return ['intents' => 'table missing'];
        }

        return [
            'total' => DB::table($table)->count(),
            'by_kind' => $this->grouped($table, 'kind'),
            'by_status' => $this->grouped($table, 'status'),
            'by_visibility' => $this->grouped($table, 'visibility'),
            'by_schedule_kind' => $this->grouped($table, 'schedule_kind'),
            'with_recurrence' => DB::table($table)->where(fn ($query) => $query
                ->whereNotNull('recurrence_interval')
                ->orWhereNotNull('recurrence_weekdays')
                ->orWhereNotNull('recurrence_day_of_month'))->count(),
            'with_time_window' => DB::table($table)->where(fn ($query) => $query
                ->whereNotNull('time_window_start')
                ->orWhereNotNull('time_window_end'))->count(),
            'with_price' => DB::table($table)->where(fn ($query) => $query
                ->whereNotNull('cash_min')
                ->orWhereNotNull('cash_max'))->count(),
            'deals_started_from_intents' => Schema::hasColumn('relationships', 'originating_intent_id')
                ? DB::table('relationships')->whereNotNull('originating_intent_id')->count()
                : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function businesses(): array
    {
        return [
            'businesses_by_kind' => $this->grouped('businesses', 'kind'),
            'memberships_by_role' => $this->grouped('business_memberships', 'role'),
            'local_categories' => $this->count('business_categories'),
            'listings_by_type' => $this->grouped('business_listings', 'listing_type'),
            'listings_by_status' => $this->grouped('business_listings', 'status'),
            'listing_versions' => $this->count('business_listing_versions'),
            'price_versions' => $this->count('business_price_versions'),
        ];
    }

    /** @return array<string, mixed> */
    private function work(): array
    {
        $terms = 'contract_service_terms';

        return [
            'deals_by_status' => $this->grouped('relationships', 'status'),
            'proposals_by_status' => $this->grouped('proposals', 'status'),
            'contracts_by_status' => $this->grouped('contracts', 'status'),
            'service_terms' => $this->count($terms),
            'service_terms_auto_create_plan' => Schema::hasTable($terms) ? DB::table($terms)->where('auto_create_plan', true)->count() : 0,
            'service_terms_with_plan_frequency' => Schema::hasTable($terms) ? DB::table($terms)->whereNotNull('plan_frequency')->count() : 0,
            'service_terms_obligation_flag_on' => Schema::hasTable($terms) ? DB::table($terms)->where('auto_recognize_obligation', true)->count() : 0,
            'commitments_by_status' => $this->grouped('commitments', 'status'),
            'commitment_plan_bindings' => $this->count('commitment_plan_bindings'),
            'fulfillments_by_status' => $this->grouped('fulfillments', 'status'),
        ];
    }

    /** @return array<string, mixed> */
    private function conversations(): array
    {
        $byContextKind = [];
        if (Schema::hasTable('conversations') && Schema::hasTable('contexts')) {
            $byContextKind = DB::table('conversations')
                ->join('contexts', 'contexts.id', '=', 'conversations.context_id')
                ->selectRaw('contexts.kind as kind, count(*) as total')
                ->groupBy('contexts.kind')
                ->pluck('total', 'kind')
                ->map(fn ($total): int => (int) $total)
                ->all();
        }

        return [
            'conversations_by_context_kind' => $byContextKind,
            'messages' => $this->count('conversation_messages'),
            'message_attachments' => $this->count('conversation_message_assets'),
            'message_revisions' => $this->count('conversation_message_revisions'),
        ];
    }

    /** @return array<string, mixed> */
    private function bookkeeping(): array
    {
        return [
            'books' => $this->count('bk_ledgers'),
            'transactions_by_type' => $this->grouped('bk_transactions', 'type'),
            'transactions_by_status' => $this->grouped('bk_transactions', 'status'),
            'with_idempotency_key' => Schema::hasTable('bk_transactions') ? DB::table('bk_transactions')->whereNotNull('idempotency_key')->count() : 0,
        ];
    }

    private function count(string $table): int|string
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 'table missing';
    }

    private function countWhere(string $table, string $column, int|string $value): int
    {
        return Schema::hasTable($table) ? DB::table($table)->where($column, $value)->count() : 0;
    }

    /** @return array<string, int>|string */
    private function grouped(string $table, string $column): array|string
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 'table missing';
        }

        return DB::table($table)
            ->selectRaw($column.' as value, count(*) as total')
            ->groupBy($column)
            ->orderBy($column)
            ->get()
            ->mapWithKeys(fn ($row): array => [($row->value ?? '(empty)') => (int) $row->total])
            ->all();
    }
}
