<?php

namespace App\Support\Profile;

use App\ConceptAssertionPredicate;
use App\ProfileIntentKind;
use App\ProfileRequirementKind;
use InvalidArgumentException;

final readonly class ProfileRequirement
{
    public function __construct(
        public ProfileRequirementKind $kind,
        public string $key,
        public string $label,
    ) {
        if ($kind === ProfileRequirementKind::Field && ! ProfileFieldCatalog::supports($key)) {
            throw new InvalidArgumentException('Unsupported Profile field requirement.');
        }
    }

    public static function field(string $field, string $label): self
    {
        return new self(ProfileRequirementKind::Field, $field, $label);
    }

    public static function concept(ConceptAssertionPredicate $predicate, string $label): self
    {
        return new self(ProfileRequirementKind::ConceptPredicate, $predicate->value, $label);
    }

    public static function activeIntent(ProfileIntentKind $kind, string $label): self
    {
        return new self(ProfileRequirementKind::ActiveIntent, $kind->value, $label);
    }
}
