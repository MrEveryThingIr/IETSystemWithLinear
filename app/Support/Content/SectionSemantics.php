<?php

namespace App\Support\Content;

use App\Models\SpaceContentBlock;
use App\Models\SpaceContentRevision;

/**
 * What a section of Content *is* (its role) and the free tags an author gives
 * it, stored with the block as data.semantics = {role, tags}.
 *
 * The role decides which reader actions are offered first (a question offers
 * "Answer", a definition offers "Remember") and how a response is recorded.
 * Responses stay ordinary annotations anchored to the section, so anything
 * built later — rewards for answering, rating correct answers, progress —
 * can be derived from: section role + tags + annotation kind + endorsements
 * (an endorsement of an answer by someone allowed to endorse is "correct").
 */
final class SectionSemantics
{
    /**
     * role => reader actions in display order, whether readers can answer it,
     * and the icon used for its badge.
     *
     * @var array<string, array{actions: list<string>, answerable: bool, icon: string}>
     */
    public const ROLES = [
        'question' => ['actions' => ['answer', 'question', 'note', 'remember'], 'answerable' => true, 'icon' => 'question-mark-circle'],
        'exercise' => ['actions' => ['answer', 'question', 'file', 'note'], 'answerable' => true, 'icon' => 'pencil-square'],
        'discussion' => ['actions' => ['comment', 'question', 'idea', 'note'], 'answerable' => false, 'icon' => 'chat-bubble-left-right'],
        'definition' => ['actions' => ['remember', 'question', 'translate', 'note'], 'answerable' => false, 'icon' => 'book-open'],
        'key_point' => ['actions' => ['remember', 'note', 'question', 'comment'], 'answerable' => false, 'icon' => 'star'],
        'example' => ['actions' => ['question', 'note', 'idea', 'remember'], 'answerable' => false, 'icon' => 'light-bulb'],
        'summary' => ['actions' => ['remember', 'note', 'question', 'comment'], 'answerable' => false, 'icon' => 'queue-list'],
    ];

    /** Offered on every section, after the role's own actions. */
    public const DEFAULT_ACTIONS = ['remember', 'note', 'question', 'comment'];

    /** Always available under "More". */
    public const MORE_ACTIONS = ['translate', 'file', 'voice', 'correction', 'idea', 'advanced'];

    public const MAX_TAGS = 8;

    /** Block types that cannot carry a role. */
    private const UNTAGGABLE = [SpaceContentBlock::TYPE_DIVIDER];

    /** @return list<string> */
    public static function roles(): array
    {
        return array_keys(self::ROLES);
    }

    public static function supports(string $blockType): bool
    {
        return ! in_array($blockType, self::UNTAGGABLE, true);
    }

    public static function isAnswerable(?string $role): bool
    {
        return $role !== null && (self::ROLES[$role]['answerable'] ?? false);
    }

    /**
     * Primary actions for a role (or the defaults), then the rest of the
     * everyday actions without repeats.
     *
     * @return array{primary: list<string>, more: list<string>}
     */
    public static function actionsFor(?string $role): array
    {
        $primary = $role !== null && isset(self::ROLES[$role]) ? self::ROLES[$role]['actions'] : self::DEFAULT_ACTIONS;
        $rest = array_values(array_diff([...self::DEFAULT_ACTIONS, ...self::MORE_ACTIONS], $primary));

        return ['primary' => $primary, 'more' => $rest];
    }

    /**
     * Role => actions, for the reader's selection toolbar script.
     *
     * @return array<string, array{primary: list<string>, more: list<string>}>
     */
    public static function actionMap(): array
    {
        $map = ['' => self::actionsFor(null)];
        foreach (self::roles() as $role) {
            $map[$role] = self::actionsFor($role);
        }

        return $map;
    }

    /**
     * Clean author input. Accepts tags as a list or as "a, b, c" text.
     *
     * @param  array<string, mixed>  $input
     * @return array{role?: string, tags?: list<string>}
     */
    public static function normalize(string $blockType, array $input): array
    {
        if (! self::supports($blockType)) {
            return [];
        }

        $normalized = [];
        $role = is_string($input['role'] ?? null) ? trim($input['role']) : '';
        if (isset(self::ROLES[$role])) {
            $normalized['role'] = $role;
        }

        $tags = $input['tags'] ?? ($input['tags_text'] ?? []);
        if (is_string($tags)) {
            $tags = preg_split('/[,،\n]+/u', $tags) ?: [];
        }
        $clean = [];
        foreach (is_array($tags) ? $tags : [] as $tag) {
            if (! is_scalar($tag)) {
                continue;
            }
            $tag = mb_strtolower(trim(preg_replace('/\s+/u', '-', trim((string) $tag)) ?? ''), 'UTF-8');
            $tag = trim((string) preg_replace('/[^\p{L}\p{N}_-]+/u', '', $tag), '-_');
            if ($tag !== '' && mb_strlen($tag) <= 40 && ! in_array($tag, $clean, true)) {
                $clean[] = $tag;
            }
        }
        if ($clean !== []) {
            $normalized['tags'] = array_slice($clean, 0, self::MAX_TAGS);
        }

        return $normalized;
    }

    /** @return array{role: ?string, tags: list<string>} */
    public static function of(SpaceContentBlock $block): array
    {
        $semantics = is_array($block->data['semantics'] ?? null) ? $block->data['semantics'] : [];

        return [
            'role' => is_string($semantics['role'] ?? null) && isset(self::ROLES[$semantics['role']]) ? $semantics['role'] : null,
            'tags' => array_values(array_filter(is_array($semantics['tags'] ?? null) ? $semantics['tags'] : [], 'is_string')),
        ];
    }

    /** The role of a block in an edition, by the block's uuid. */
    public static function roleOfBlock(SpaceContentRevision $revision, ?string $blockUuid): ?string
    {
        if ($blockUuid === null || $blockUuid === '') {
            return null;
        }

        $block = $revision->blocks()->where('uuid', $blockUuid)->first();

        return $block instanceof SpaceContentBlock ? self::of($block)['role'] : null;
    }
}
