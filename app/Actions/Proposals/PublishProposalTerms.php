<?php

namespace App\Actions\Proposals;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Groups\PublishSpaceContent;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\Context;
use App\Models\SpaceContentRevision;
use App\Models\User;

class PublishProposalTerms
{
    public function __construct(
        private readonly EnsureSystemContentBlueprints $blueprints,
        private readonly CreateContentFromBlueprint $create,
        private readonly PublishSpaceContent $publish,
    ) {}

    public function execute(
        Context $context,
        User $user,
        string $title,
        string $terms,
        ?string $summary = null,
        ?string $notes = null,
    ): SpaceContentRevision {
        $blueprint = $this->blueprints->execute()
            ->firstWhere('slug', 'proposal-terms');

        abort_unless($blueprint instanceof ContentBlueprint, 500, 'Proposal terms Blueprint is unavailable.');

        $version = $blueprint->activeVersion;
        abort_unless($version instanceof ContentBlueprintVersion, 500, 'Proposal terms Blueprint has no active version.');

        $content = $this->create->execute(
            $context,
            $version,
            $user,
            $title,
            [
                'summary' => $summary,
                'terms' => $terms,
                'notes' => $notes,
            ],
        );

        $content = $this->publish->execute($content, $user);
        $revision = $content->activeRevisionRecord();

        abort_unless(
            $revision instanceof SpaceContentRevision && $revision->hasVerifiableManifest(),
            500,
            'Proposal terms must be a sealed published Content revision.',
        );

        return $revision;
    }
}
