<?php

use App\Http\Controllers\ActorAvatarController;
use App\Http\Controllers\ActorProfileImageController;
use App\Http\Controllers\ActorProfileReferenceController;
use App\Http\Controllers\AdmissionContextContentController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ContentEvidenceReferenceController;
use App\Http\Controllers\ContentRevisionController;
use App\Http\Controllers\GroupInvitationController;
use App\Http\Controllers\LegacyGroupContentRedirectController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MyContextContentController;
use App\Http\Controllers\SpaceContentAssetController;
use App\Livewire\Actors\Create;
use App\Livewire\Actors\Index;
use App\Livewire\Actors\Show;
use App\Livewire\Admissions\Show as AdmissionShow;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmailNotice;
use App\Livewire\Contexts\ContentAppearance as ContextContentAppearance;
use App\Livewire\Contexts\ContentBlocks as ContextContentBlocks;
use App\Livewire\Contexts\ContentIndex as ContextContentIndex;
use App\Livewire\Contexts\ContentOutline as ContextContentOutline;
use App\Livewire\Contexts\ContentShow as ContextContentShow;
use App\Livewire\Contexts\ContentStudio as ContextContentStudio;
use App\Livewire\Groups\AcceptAgreements;
use App\Livewire\Groups\Agreements;
use App\Livewire\Groups\Create as CreateGroup;
use App\Livewire\Groups\Index as GroupIndex;
use App\Livewire\Groups\Invitations;
use App\Livewire\Groups\Show as GroupShow;
use App\Livewire\Groups\SpaceChat;
use App\Livewire\Groups\SpaceManagement;
use App\Livewire\Platform\Access as PlatformAccess;
use App\Livewire\Profile\Manage as ProfileManage;
use App\Livewire\Profile\SharedShow;
use App\Livewire\Profile\Show as ProfileShow;
use App\Models\Actor;
use App\Models\Group;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));
Route::post('/locale', LocaleController::class)->name('locale.update');
Route::get('/people/{actor}', ActorProfileReferenceController::class)->name('actors.profile.reference');
Route::get('/people/{actor}/avatar', ActorAvatarController::class)->name('actors.avatar');
Route::livewire('/profiles/{profile}', ProfileShow::class)->name('profiles.show');
Route::get('/profiles/{profile}/images/{image}', [ActorProfileImageController::class, 'show'])
    ->name('profiles.images.show');
Route::get('/invitations/{token}', [GroupInvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [GroupInvitationController::class, 'accept'])->middleware(['auth', 'account.active', 'verified', 'throttle:invitation-acceptance'])->name('invitations.accept');
Route::middleware('guest')->group(function (): void {
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
});
Route::middleware(['auth', 'account.active', 'verified'])->group(function (): void {
    Route::livewire('/profile', ProfileManage::class)->name('profile.edit');
    Route::get('/my-content', MyContextContentController::class)->name('contexts.personal');
    Route::get('/admissions/{admission}/content', AdmissionContextContentController::class)->name('admissions.context.contents');
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
    Route::get('/content-evidence/{reference}', ContentEvidenceReferenceController::class)->name('content-evidence.show');
    Route::livewire('/profile-shares/{grant}', SharedShow::class)->name('profiles.shares.show');
    Route::livewire('/platform/access', PlatformAccess::class)->name('platform.access');
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
