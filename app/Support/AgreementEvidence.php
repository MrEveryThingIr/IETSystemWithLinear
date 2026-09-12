<?php

namespace App\Support;

use App\Models\Actor;
use App\Models\AgreementAcceptance;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use App\Models\User;
use Carbon\CarbonImmutable;

class AgreementEvidence
{
    public const HASH_ALGORITHM = 'sha256';

    public const SCHEMA_VERSION = 1;

    /**
     * @return array{
     *     group_agreement_id: int,
     *     version_number: int,
     *     evidence_hash: string,
     *     hash_algorithm: string,
     *     version_effective_from: \DateTimeInterface|null,
     *     version_effective_until: \DateTimeInterface|null,
     *     required_for_admission: bool,
     *     reacceptance_required: bool,
     *     accepted_by_actor_id: int,
     *     represented_actor_id: int,
     *     acting_user_id: int|null,
     *     accepted_at: CarbonImmutable,
     *     evidence_schema_version: int
     * }
     */
    public static function forAcceptance(
        GroupAgreementVersion $version,
        Actor $acceptingActor,
        ?Actor $representedActor = null,
        ?User $actingUser = null,
    ): array {
        /** @var GroupAgreement $agreement */
        $agreement = $version->agreement()->firstOrFail();
        $contentHash = $version->content_hash ?: GroupAgreementVersion::hashContent($version->content);
        $representedActor ??= $acceptingActor;

        return [
            'group_agreement_id' => $agreement->id,
            'version_number' => $version->version,
            'evidence_hash' => $contentHash,
            'hash_algorithm' => self::HASH_ALGORITHM,
            'version_effective_from' => $version->effective_from,
            'version_effective_until' => $version->effective_until,
            'required_for_admission' => $agreement->required_for_admission,
            'reacceptance_required' => $version->reacceptance_required,
            'accepted_by_actor_id' => $acceptingActor->id,
            'represented_actor_id' => $representedActor->id,
            'acting_user_id' => $actingUser !== null ? $actingUser->id : $acceptingActor->user_id,
            'accepted_at' => CarbonImmutable::now(),
            'evidence_schema_version' => self::SCHEMA_VERSION,
        ];
    }

    public static function matchesAdmissionAcceptance(
        AgreementAcceptance $acceptance,
        GroupAgreementVersion $version,
        Actor $acceptingActor,
    ): bool {
        /** @var GroupAgreement $agreement */
        $agreement = $version->agreement()->firstOrFail();

        return (int) $acceptance->group_agreement_id === (int) $agreement->id
            && (int) $acceptance->version_number === (int) $version->version
            && hash_equals($version->content_hash, $acceptance->evidence_hash)
            && $acceptance->hash_algorithm === self::HASH_ALGORITHM
            && self::sameInstant($acceptance->version_effective_from, $version->effective_from)
            && self::sameInstant($acceptance->version_effective_until, $version->effective_until)
            && $acceptance->required_for_admission === (bool) $agreement->required_for_admission
            && $acceptance->reacceptance_required === (bool) $version->reacceptance_required
            && (int) $acceptance->accepted_by_actor_id === (int) $acceptingActor->id
            && (int) $acceptance->represented_actor_id === (int) $acceptingActor->id
            && $acceptance->acting_user_id === $acceptingActor->user_id
            && (int) $acceptance->evidence_schema_version === self::SCHEMA_VERSION;
    }

    private static function sameInstant(?\DateTimeInterface $first, ?\DateTimeInterface $second): bool
    {
        if ($first === null || $second === null) {
            return $first === $second;
        }

        return CarbonImmutable::instance($first)->equalTo($second);
    }
}
