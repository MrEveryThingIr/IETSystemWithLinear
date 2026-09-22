<?php

namespace App\Support;

use App\Models\InteractionDefinitionVersion;
use Illuminate\Validation\ValidationException;

class EvaluationDataNormalizer
{
    /**
     * @param  list<mixed>  $criterionResults
     * @return array{feedback: ?string, score: ?string, criterion_results: list<array{key: string, score: string, feedback: ?string}>}
     */
    public function normalize(
        InteractionDefinitionVersion $version,
        ?string $feedback,
        int|float|string|null $score,
        array $criterionResults,
    ): array {
        $config = $version->evaluation_config;
        if (($config['mode'] ?? 'manual') !== 'manual') {
            $this->invalid('evaluation', 'This interaction does not accept evaluations.');
        }

        $feedback = $feedback !== null ? trim($feedback) : null;
        $feedback = $feedback === '' ? null : $feedback;
        if ($feedback !== null && mb_strlen($feedback) > 20000) {
            $this->invalid('feedback', 'Evaluation feedback may not exceed 20000 characters.');
        }

        $scoreMax = $config['score_max'] ?? null;
        $normalizedScore = $score === null || $score === ''
            ? null
            : $this->score($score, $scoreMax, 'score');

        $definitions = is_array($config['criteria'] ?? null) ? $config['criteria'] : [];
        $normalizedCriteria = $this->criteria($definitions, $criterionResults);

        return [
            'feedback' => $feedback,
            'score' => $normalizedScore,
            'criterion_results' => $normalizedCriteria,
        ];
    }

    /**
     * @param  list<mixed>  $definitions
     * @param  list<mixed>  $results
     * @return list<array{key: string, score: string, feedback: ?string}>
     */
    private function criteria(array $definitions, array $results): array
    {
        if ($results === []) {
            return [];
        }
        if ($definitions === [] || count($results) > count($definitions)) {
            $this->invalid('criteria', 'Evaluation criterion results do not match the configured rubric.');
        }

        $resultMap = [];
        foreach ($results as $result) {
            if (! is_array($result)
                || array_diff(array_keys($result), ['key', 'score', 'feedback']) !== []) {
                $this->invalid('criteria', 'Each criterion result may contain only key, score, and feedback.');
            }

            $key = strtolower(trim((string) ($result['key'] ?? '')));
            if ($key === '' || isset($resultMap[$key])) {
                $this->invalid('criteria', 'Evaluation criterion keys must be unique.');
            }

            $resultMap[$key] = $result;
        }

        $normalized = [];
        foreach ($definitions as $definition) {
            if (! is_array($definition) || ! is_string($definition['key'] ?? null)) {
                $this->invalid('criteria', 'The configured evaluation rubric is invalid.');
            }

            $key = $definition['key'];
            if (! isset($resultMap[$key])) {
                continue;
            }

            $result = $resultMap[$key];
            $criterionFeedback = isset($result['feedback']) ? trim((string) $result['feedback']) : null;
            $criterionFeedback = $criterionFeedback === '' ? null : $criterionFeedback;
            if ($criterionFeedback !== null && mb_strlen($criterionFeedback) > 5000) {
                $this->invalid("criteria.{$key}", 'Criterion feedback may not exceed 5000 characters.');
            }

            $normalized[] = [
                'key' => $key,
                'score' => $this->score(
                    $result['score'] ?? null,
                    $definition['score_max'] ?? null,
                    "criteria.{$key}.score",
                ),
                'feedback' => $criterionFeedback,
            ];

            unset($resultMap[$key]);
        }

        if ($resultMap !== []) {
            $this->invalid('criteria', 'Evaluation contains a criterion that is not part of the configured rubric.');
        }

        return $normalized;
    }

    private function score(mixed $value, mixed $max, string $key): string
    {
        if ((! is_int($value) && ! is_float($value) && ! is_string($value))
            || ! is_numeric((string) $value)) {
            $this->invalid($key, 'Evaluation score must be numeric.');
        }

        $number = (float) $value;
        $maximum = $max === null ? 1000000.0 : (float) $max;

        if (! is_finite($number) || $number < 0 || $number > $maximum) {
            $this->invalid($key, 'Evaluation score is outside the allowed range.');
        }

        $normalized = rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');

        return $normalized === '' ? '0' : $normalized;
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
