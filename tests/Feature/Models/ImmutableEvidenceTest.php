<?php

namespace Tests\Feature\Models;

use App\Models\Actor;
use App\Models\Admission;
use App\Models\AdmissionEvent;
use App\Models\AgreementEvent;
use App\Models\Group;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ImmutableEvidenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admission_with_event_evidence_cannot_be_deleted(): void
    {
        $actor = Actor::factory()->create();
        $group = Group::create(['name' => 'Group', 'created_by_actor_id' => $actor->id]);
        $admission = Admission::create(['group_id' => $group->id, 'candidate_actor_id' => $actor->id]);
        AdmissionEvent::create(['admission_id' => $admission->id, 'event' => 'admission.created']);

        $this->expectException(QueryException::class);

        $admission->delete();
    }

    public function test_admission_event_evidence_cannot_be_mutated_or_deleted(): void
    {
        $actor = Actor::factory()->create();
        $group = Group::create(['name' => 'Group', 'created_by_actor_id' => $actor->id]);
        $admission = Admission::create(['group_id' => $group->id, 'candidate_actor_id' => $actor->id]);
        $event = AdmissionEvent::create(['admission_id' => $admission->id, 'event' => 'admission.created']);

        try {
            $event->update(['event' => 'admission.changed']);
            $this->fail('Expected immutable evidence exception.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->expectException(HttpException::class);
        $event->delete();
    }

    public function test_agreement_event_evidence_cannot_be_mutated_or_deleted(): void
    {
        $actor = Actor::factory()->create();
        $group = Group::create(['name' => 'Group', 'created_by_actor_id' => $actor->id]);
        $agreement = GroupAgreement::create(['group_id' => $group->id, 'name' => 'Rules']);
        $version = GroupAgreementVersion::create(['group_agreement_id' => $agreement->id, 'version' => 1, 'content' => 'Terms']);
        $event = AgreementEvent::create(['group_agreement_id' => $agreement->id, 'group_agreement_version_id' => $version->id, 'event' => 'agreement.created']);

        try {
            $event->update(['event' => 'agreement.changed']);
            $this->fail('Expected immutable evidence exception.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->expectException(HttpException::class);
        $event->delete();
    }
}
