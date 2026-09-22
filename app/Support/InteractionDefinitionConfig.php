<?php

namespace App\Support;

use LogicException;

class InteractionDefinitionConfig
{
    public function __construct(private readonly InteractionResponseTypeRegistry $responseTypes) {}

    /**
     * @param  list<mixed>  $items
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $evaluationConfig
     * @return array{
     *     purpose_key: string,
     *     title: string,
     *     instructions: ?string,
     *     items: list<array<string, mixed>>,
     *     settings: array<string, mixed>,
     *     evaluation_config: array<string, mixed>,
     *     content_hash: string
     * }
     */
    public function normalize(
        string $purposeKey,
        string $title,
        ?string $instructions,
        array $items,
        array $settings,
        array $evaluationConfig,
        ?string $contentRevisionUuid = null,
    ): array {
        $purposeKey = strtolower(trim($purposeKey));
        $title = trim($title);
        $instructions = $instructions !== null ? trim($instructions) : null;
        $instructions = $instructions === '' ? null : $instructions;

        if (! preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)*$/', $purposeKey) || mb_strlen($purposeKey) > 64) {
            throw new LogicException('Interaction purpose key is invalid.');
        }
        if ($title === '' || mb_strlen($title) > 160) {
            throw new LogicException('Interaction title is required and may not exceed 160 characters.');
        }
        if ($instructions !== null && mb_strlen($instructions) > 10000) {
            throw new LogicException('Interaction instructions may not exceed 10000 characters.');
        }

        $normalized = [
            'purpose_key' => $purposeKey,
            'title' => $title,
            'instructions' => $instructions,
            'items' => $this->items($items),
            'settings' => $this->settings($settings),
            'evaluation_config' => $this->evaluationConfig($evaluationConfig),
        ];

        $normalized['content_hash'] = SpaceContentSchema::hashArray([
            ...$normalized,
            'space_content_revision_uuid' => $contentRevisionUuid,
        ]);

        return $normalized;
    }

    /** @param list<mixed> $items @return list<array<string, mixed>> */
    private function items(array $items): array
    {
        if ($items === [] || count($items) > 200) {
            throw new LogicException('An Interaction Definition requires between 1 and 200 items.');
        }

        $normalized = [];
        $keys = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new LogicException('Every Interaction item must be structured data.');
            }

            $allowed = ['key', 'label', 'type', 'required', 'help', 'options', 'constraints'];
            if (array_diff(array_keys($item), $allowed) !== []) {
                throw new LogicException('Unknown Interaction item configuration.');
            }

            $key = strtolower(trim((string) ($item['key'] ?? '')));
            $label = trim((string) ($item['label'] ?? ''));
            $type = strtolower(trim((string) ($item['type'] ?? '')));
            $help = isset($item['help']) ? trim((string) $item['help']) : null;
            $help = $help === '' ? null : $help;

            if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
                throw new LogicException('Interaction item keys must be stable snake_case identifiers.');
            }
            if (isset($keys[$key])) {
                throw new LogicException('Interaction item keys must be unique within a version.');
            }
            if ($label === '' || mb_strlen($label) > 160) {
                throw new LogicException('Interaction item labels are required and may not exceed 160 characters.');
            }
            if (! $this->responseTypes->supports($type)) {
                throw new LogicException('Unsupported Interaction response type.');
            }
            if ($help !== null && mb_strlen($help) > 1000) {
                throw new LogicException('Interaction item help may not exceed 1000 characters.');
            }

            $options = $this->options(
                is_array($item['options'] ?? null) ? $item['options'] : [],
                $type,
            );

            $constraints = $this->constraints(
                is_array($item['constraints'] ?? null) ? $item['constraints'] : [],
            );

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'required' => (bool) ($item['required'] ?? false),
                'help' => $help,
                'options' => $options,
                'constraints' => $constraints,
            ];
            $keys[$key] = true;
        }

        return $normalized;
    }

    /** @param array<int, mixed> $options @return list<string> */
    private function options(array $options, string $type): array
    {
        if (! $this->responseTypes->usesOptions($type)) {
            if ($options !== []) {
                throw new LogicException('Only choice Interaction items may define options.');
            }

            return [];
        }

        if ($options === [] || count($options) > 100) {
            throw new LogicException('Choice Interaction items require between 1 and 100 options.');
        }

        $normalized = [];

        foreach ($options as $option) {
            $value = trim(is_scalar($option) ? (string) $option : '');

            if ($value === '' || mb_strlen($value) > 255) {
                throw new LogicException('Interaction choice options must be non-empty strings up to 255 characters.');
            }

            $normalized[] = $value;
        }

        if (count(array_unique($normalized)) !== count($normalized)) {
            throw new LogicException('Interaction choice options must be unique.');
        }

        return $normalized;
    }

    /** @param array<string, mixed> $constraints @return array<string, int|float> */
    private function constraints(array $constraints): array
    {
        $allowed = ['min', 'max', 'min_length', 'max_length'];
        if (array_diff(array_keys($constraints), $allowed) !== []) {
            throw new LogicException('Unknown Interaction item constraint.');
        }

        $normalized = [];

        foreach (['min', 'max'] as $key) {
            if (! array_key_exists($key, $constraints)) {
                continue;
            }

            $value = $constraints[$key];
            if (! is_int($value) && ! is_float($value)) {
                throw new LogicException('Interaction numeric constraints must be numbers.');
            }

            $normalized[$key] = $value;
        }

        foreach (['min_length', 'max_length'] as $key) {
            if (! array_key_exists($key, $constraints)) {
                continue;
            }

            $value = $constraints[$key];
            if (! is_int($value) || $value < 0 || $value > 100000) {
                throw new LogicException('Interaction length constraints must be bounded non-negative integers.');
            }

            $normalized[$key] = $value;
        }

        if (isset($normalized['min'], $normalized['max']) && $normalized['min'] > $normalized['max']) {
            throw new LogicException('Interaction minimum cannot exceed maximum.');
        }
        if (isset($normalized['min_length'], $normalized['max_length'])
            && $normalized['min_length'] > $normalized['max_length']) {
            throw new LogicException('Interaction minimum length cannot exceed maximum length.');
        }

        return $normalized;
    }

    /** @param array<string, mixed> $settings @return array{allow_withdrawal: bool, max_attempts: ?int} */
    private function settings(array $settings): array
    {
        $allowed = ['allow_withdrawal', 'max_attempts'];
        if (array_diff(array_keys($settings), $allowed) !== []) {
            throw new LogicException('Unknown Interaction setting.');
        }

        $maxAttempts = $settings['max_attempts'] ?? null;
        if ($maxAttempts !== null && (! is_int($maxAttempts) || $maxAttempts < 1 || $maxAttempts > 100)) {
            throw new LogicException('Interaction max attempts must be between 1 and 100 or null.');
        }

        return [
            'allow_withdrawal' => (bool) ($settings['allow_withdrawal'] ?? true),
            'max_attempts' => $maxAttempts,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{mode: string, score_max: int|float|null, criteria: list<array{key: string, label: string, score_max: int|float}>}
     */
    private function evaluationConfig(array $config): array
    {
        $allowed = ['mode', 'score_max', 'criteria'];
        if (array_diff(array_keys($config), $allowed) !== []) {
            throw new LogicException('Unknown Interaction evaluation configuration.');
        }

        $mode = strtolower(trim((string) ($config['mode'] ?? 'manual')));
        if (! in_array($mode, ['none', 'manual'], true)) {
            throw new LogicException('Unknown Interaction evaluation mode.');
        }

        $scoreMax = $config['score_max'] ?? null;
        if ($scoreMax !== null
            && (! is_int($scoreMax) && ! is_float($scoreMax) || $scoreMax <= 0 || $scoreMax > 1000000)) {
            throw new LogicException('Interaction score maximum must be a positive bounded number or null.');
        }

        $criteria = $this->evaluationCriteria(
            is_array($config['criteria'] ?? null) ? $config['criteria'] : [],
        );

        if ($mode === 'none') {
            $scoreMax = null;
            $criteria = [];
        }

        return [
            'mode' => $mode,
            'score_max' => $scoreMax,
            'criteria' => $criteria,
        ];
    }

    /** @param array<int, mixed> $criteria @return list<array{key: string, label: string, score_max: int|float}> */
    private function evaluationCriteria(array $criteria): array
    {
        if (count($criteria) > 50) {
            throw new LogicException('An Interaction evaluation rubric may contain at most 50 criteria.');
        }

        $normalized = [];
        $keys = [];

        foreach ($criteria as $criterion) {
            if (! is_array($criterion)
                || array_diff(array_keys($criterion), ['key', 'label', 'score_max']) !== []) {
                throw new LogicException('Evaluation criteria may contain only key, label, and score_max.');
            }

            $key = strtolower(trim((string) ($criterion['key'] ?? '')));
            $label = trim((string) ($criterion['label'] ?? ''));
            $scoreMax = $criterion['score_max'] ?? null;

            if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) || isset($keys[$key])) {
                throw new LogicException('Evaluation criterion keys must be unique snake_case identifiers.');
            }
            if ($label === '' || mb_strlen($label) > 160) {
                throw new LogicException('Evaluation criterion labels are required and may not exceed 160 characters.');
            }
            if ((! is_int($scoreMax) && ! is_float($scoreMax)) || $scoreMax <= 0 || $scoreMax > 1000000) {
                throw new LogicException('Evaluation criterion score maximum must be a positive bounded number.');
            }

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'score_max' => $scoreMax,
            ];
            $keys[$key] = true;
        }

        return $normalized;
    }
}
