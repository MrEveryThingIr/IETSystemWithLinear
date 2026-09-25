<?php

use App\Http\Controllers\AccessInvitationController;
use App\Http\Controllers\ActorAvatarController;
use App\Http\Controllers\ActorProfileImageController;
use App\Http\Controllers\ActorProfileReferenceController;
use App\Http\Controllers\AdmissionContextCollaborationController;
use App\Http\Controllers\AdmissionContextContentController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ContentEvidenceReferenceController;
use App\Http\Controllers\ContentRevisionController;
use App\Http\Controllers\ContextConversationAssetController;
use App\Http\Controllers\GroupInvitationController;
use App\Http\Controllers\LegacyGroupContentRedirectController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MyContextContentController;
use App\Http\Controllers\SpaceContentAssetController;
use App\Http\Controllers\SubmissionAssetController;
use App\Http\Controllers\SystemManualController;
use App\Livewire\Accounting\Index as AccountingIndex;
use App\Livewire\Actors\Create;
use App\Livewire\Actors\Index;
use App\Livewire\Actors\Show;
use App\Livewire\Admissions\Show as AdmissionShow;
use App\Livewire\Auth\AccessRegister;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmailNotice;
use App\Livewire\Commitments\Create as CommitmentCreate;
use App\Livewire\Commitments\Show as CommitmentShow;
use App\Livewire\Content\Library as ContentLibrary;
use App\Livewire\Contexts\ContentAppearance as ContextContentAppearance;
use App\Livewire\Contexts\ContentBlocks as ContextContentBlocks;
use App\Livewire\Contexts\ContentIndex as ContextContentIndex;
use App\Livewire\Contexts\ContentOutline as ContextContentOutline;
use App\Livewire\Contexts\ContentShow as ContextContentShow;
use App\Livewire\Contexts\ContentStudio as ContextContentStudio;
use App\Livewire\Contexts\Conversation as ContextConversation;
use App\Livewire\Contexts\Timeline as ContextTimeline;
use App\Livewire\Contracts\Create as ContractCreate;
use App\Livewire\Contracts\Index as ContractIndex;
use App\Livewire\Contracts\Show as ContractShow;
use App\Livewire\Financial\Show as FinancialObligationShow;
use App\Livewire\Groups\AcceptAgreements;
use App\Livewire\Groups\Agreements;
use App\Livewire\Groups\Create as CreateGroup;
use App\Livewire\Groups\Index as GroupIndex;
use App\Livewire\Groups\Invitations;
use App\Livewire\Groups\Show as GroupShow;
use App\Livewire\Groups\SpaceChat;
use App\Livewire\Groups\SpaceManagement;
use App\Livewire\Intents\Create as IntentCreate;
use App\Livewire\Intents\Directory as IntentDirectory;
use App\Livewire\Interactions\ReviewQueue;
use App\Livewire\Interactions\ReviewShow;
use App\Livewire\Journeys\Index as JourneyIndex;
use App\Livewire\Planner\Create as PlannerCreate;
use App\Livewire\Planner\Index as PlannerIndex;
use App\Livewire\Planner\Show as PlannerShow;
use App\Livewire\Platform\Access as PlatformAccess;
use App\Livewire\Platform\AccessInvitations;
use App\Livewire\Profile\Manage as ProfileManage;
use App\Livewire\Profile\SharedShow;
use App\Livewire\Profile\Show as ProfileShow;
use App\Livewire\Proposals\Create as ProposalCreate;
use App\Livewire\Proposals\Index as ProposalIndex;
use App\Livewire\Proposals\Show as ProposalShow;
use App\Livewire\Relationships\Create as RelationshipCreate;
use App\Livewire\Relationships\Index as RelationshipIndex;
use App\Livewire\Relationships\Show as RelationshipShow;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\Group;
use App\Models\Proposal;
use App\Models\Relationship;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));
Route::get('/join/{token}', [AccessInvitationController::class, 'show'])->name('access-invitations.show');
Route::post('/locale', LocaleController::class)->name('locale.update');
Route::get('/people/{actor}', ActorProfileReferenceController::class)->name('actors.profile.reference');
Route::get('/people/{actor}/avatar', ActorAvatarController::class)->name('actors.avatar');
Route::livewire('/profiles/{profile}', ProfileShow::class)->name('profiles.show');
Route::get('/profiles/{profile}/images/{image}', [ActorProfileImageController::class, 'show'])
    ->name('profiles.images.show');
Route::get('/invitations/{token}', [GroupInvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [GroupInvitationController::class, 'accept'])->middleware(['auth', 'account.active', 'verified', 'throttle:invitation-acceptance'])->name('invitations.accept');
Route::middleware('guest')->group(function (): void {
    Route::livewire('/join/{token}/register', AccessRegister::class)->name('access-invitations.register');
    // Legacy compatibility for historical Group invitation links. New accounts use Access Invitations.
    Route::livewire('/invitations/{token}/register', Register::class)->name('invitations.register');
    Route::livewire('/invitations/{token}/login', Login::class)->name('invitations.login');
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::livewire('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});
Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');
Route::middleware(['auth', 'account.active'])->group(function (): void {
    Route::livewire('/email/verify', VerifyEmailNotice::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::view('/dashboard', 'dashboard')->middleware('verified')->name('dashboard');
    Route::view('/getting-started', 'getting-started')->middleware('verified')->name('getting-started');
});
Route::middleware(['auth', 'account.active', 'verified'])->group(function (): void {
    Route::livewire('/profile', ProfileManage::class)->name('profile.edit');
    Route::livewire('/intents', IntentDirectory::class)->name('intents.index');
    Route::livewire('/intents/create', IntentCreate::class)->name('intents.create');
    Route::livewire('/journeys', JourneyIndex::class)->name('journeys.index');
    Route::livewire('/relationships', RelationshipIndex::class)->can('viewAny', Relationship::class)->name('relationships.index');
    Route::livewire('/relationships/create', RelationshipCreate::class)->can('create', Relationship::class)->name('relationships.create');
    Route::livewire('/relationships/{relationship}', RelationshipShow::class)->can('view', 'relationship')->name('relationships.show');
    Route::livewire('/proposals', ProposalIndex::class)->can('viewAny', Proposal::class)->name('proposals.index');
    Route::livewire('/proposals/create', ProposalCreate::class)->can('create', Proposal::class)->name('proposals.create');
    Route::livewire('/proposals/{proposal}', ProposalShow::class)->can('view', 'proposal')->name('proposals.show');
    Route::livewire('/contracts', ContractIndex::class)->can('viewAny', Contract::class)->name('contracts.index');
    Route::livewire('/contracts/create', ContractCreate::class)->can('create', Contract::class)->name('contracts.create');
    Route::livewire('/contracts/{contract}/commitments/create', CommitmentCreate::class)->name('commitments.create');
    Route::livewire('/contracts/{contract}', ContractShow::class)->can('view', 'contract')->name('contracts.show');
    Route::livewire('/commitments/{commitment}', CommitmentShow::class)->can('view', 'commitment')->name('commitments.show');
    Route::livewire('/financial-obligations/{obligation}', FinancialObligationShow::class)
        ->can('view', 'obligation')
        ->name('financial-obligations.show');
    Route::livewire('/planner', PlannerIndex::class)->name('planner.index');
    Route::livewire('/planner/create', PlannerCreate::class)->name('planner.create');
    Route::livewire('/planner/{plan}', PlannerShow::class)->can('view', 'plan')->name('planner.show');
    Route::livewire('/accounting', AccountingIndex::class)->name('accounting.index');
    Route::livewire('/library', ContentLibrary::class)->name('content.library');
    Route::get('/my-content', MyContextContentController::class)->name('contexts.personal');
    Route::get('/manual', SystemManualController::class)->name('manual');
    Route::get('/admissions/{admission}/content', AdmissionContextContentController::class)->name('admissions.context.contents');
    Route::get('/admissions/{admission}/conversation', [AdmissionContextCollaborationController::class, 'conversation'])->name('admissions.context.conversation');
    Route::get('/admissions/{admission}/timeline', [AdmissionContextCollaborationController::class, 'timeline'])->name('admissions.context.timeline');
    Route::livewire('/contexts/{context}/conversation', ContextConversation::class)->can('view', 'context')->name('contexts.conversation');
    Route::livewire('/contexts/{context}/timeline', ContextTimeline::class)->can('view', 'context')->name('contexts.timeline');
    Route::get('/contexts/{context}/conversation/{message}/assets/{asset}', [ContextConversationAssetController::class, 'show'])
        ->name('contexts.conversation.assets.show');
    Route::get('/contexts/{context}/conversation/{message}/assets/{asset}/download', [ContextConversationAssetController::class, 'download'])
        ->name('contexts.conversation.assets.download');
    Route::livewire('/contexts/{context}/contents', ContextContentIndex::class)->can('view', 'context')->name('contexts.contents.index');
    Route::get('/contexts/{context}/contents/{content}/assets/{asset}', [SpaceContentAssetController::class, 'showContext'])
        ->name('contexts.contents.assets.show');
    Route::get('/contexts/{context}/contents/{content}/assets/{asset}/download', [SpaceContentAssetController::class, 'downloadContext'])
        ->name('contexts.contents.assets.download');
    Route::livewire('/contexts/{context}/contents/{content}/studio', ContextContentStudio::class)->name('contexts.contents.studio');
    Route::livewire('/contexts/{context}/contents/{content}/studio/blocks', ContextContentBlocks::class)->name('contexts.contents.blocks');
    Route::livewire('/contexts/{context}/contents/{content}/studio/appearance', ContextContentAppearance::class)->name('contexts.contents.appearance');
    Route::livewire('/contexts/{context}/contents/{content}/outline', ContextContentOutline::class)->name('contexts.contents.outline');
    Route::get('/contexts/{context}/contents/{content}/revisions/{revision}', ContentRevisionController::class)
        ->name('contexts.contents.revisions.show');
    Route::livewire('/contexts/{context}/contents/{content}', ContextContentShow::class)->name('contexts.contents.show');
    Route::livewire('/contexts/{context}/submissions', ReviewQueue::class)->name('contexts.submissions.index');
    Route::livewire('/contexts/{context}/submissions/{submission}', ReviewShow::class)->name('contexts.submissions.show');
    Route::get('/contexts/{context}/submissions/{submission}/assets/{asset}', [SubmissionAssetController::class, 'show'])
        ->name('contexts.submissions.assets.show');
    Route::get('/contexts/{context}/submissions/{submission}/assets/{asset}/download', [SubmissionAssetController::class, 'download'])
        ->name('contexts.submissions.assets.download');
    Route::get('/content-evidence/{reference}', ContentEvidenceReferenceController::class)->name('content-evidence.show');
    Route::livewire('/profile-shares/{grant}', SharedShow::class)->name('profiles.shares.show');
    Route::livewire('/platform/access', PlatformAccess::class)->name('platform.access');
    Route::livewire('/platform/access-invitations', AccessInvitations::class)->name('platform.access-invitations');
    Route::livewire('/actors', Index::class)->can('viewAny', Actor::class)->name('actors.index');
    Route::livewire('/actors/create', Create::class)->can('create', Actor::class)->name('actors.create');
    Route::livewire('/actors/{actor}', Show::class)->can('view', 'actor')->name('actors.show');
    Route::livewire('/groups', GroupIndex::class)->name('groups.index');
    Route::livewire('/groups/create', CreateGroup::class)->can('create', Group::class)->name('groups.create');
    Route::livewire('/groups/{group}/accept-agreements', AcceptAgreements::class)->name('groups.accept-agreements');
    Route::livewire('/groups/{group}/spaces/manage', SpaceManagement::class)->name('groups.spaces.manage');
    Route::get('/groups/{group}/spaces/{space}/contents', [LegacyGroupContentRedirectController::class, 'index'])->name('groups.spaces.contents.index');
    Route::get('/groups/{group}/spaces/{space}/contents/{content}/assets/{asset}', [SpaceContentAssetController::class, 'show'])
        ->name('groups.spaces.contents.assets.show');
    Route::get('/groups/{group}/spaces/{space}/contents/{content}/assets/{asset}/download', [SpaceContentAssetController::class, 'download'])
        ->name('groups.spaces.contents.assets.download');
    Route::get('/groups/{group}/spaces/{space}/contents/{content}/studio', [LegacyGroupContentRedirectController::class, 'studio'])
        ->name('groups.spaces.contents.studio');
    Route::get('/groups/{group}/spaces/{space}/contents/{content}/studio/blocks', [LegacyGroupContentRedirectController::class, 'blocks'])
        ->name('groups.spaces.contents.blocks');
    Route::get('/groups/{group}/spaces/{space}/contents/{content}/studio/appearance', [LegacyGroupContentRedirectController::class, 'appearance'])
        ->name('groups.spaces.contents.appearance');
    Route::get('/groups/{group}/spaces/{space}/contents/{content}/outline', [LegacyGroupContentRedirectController::class, 'outline'])
        ->name('groups.spaces.contents.outline');
    Route::get('/groups/{group}/spaces/{space}/contents/{content}/structure', [LegacyGroupContentRedirectController::class, 'outline'])
        ->name('groups.spaces.contents.structure');
    Route::get('/groups/{group}/spaces/{space}/contents/{content}', [LegacyGroupContentRedirectController::class, 'show'])
        ->name('groups.spaces.contents.show');
    Route::livewire('/groups/{group}/spaces/{space}', SpaceChat::class)->name('groups.spaces.show');
    Route::livewire('/groups/{group}', GroupShow::class)->name('groups.show');
    Route::livewire('/groups/{group}/agreements', Agreements::class)->name('groups.agreements');
    Route::livewire('/groups/{group}/invitations', Invitations::class)->name('groups.invitations');
    Route::livewire('/admissions/{admission}', AdmissionShow::class)->name('admissions.show');
});
