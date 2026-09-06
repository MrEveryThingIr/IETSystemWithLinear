<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_users_contains_only_account_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id', 'username', 'email', 'email_verified_at', 'password',
            'status', 'remember_token', 'created_at', 'updated_at',
        ], Schema::getColumnListing('users'));
    }

    public function test_new_accounts_default_to_active_and_allow_unverified_email(): void
    {
        $user = User::factory()->unverified()->create()->refresh();

        $this->assertSame('active', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertInstanceOf(Carbon::class, $user->created_at);
        $this->assertInstanceOf(Carbon::class, $user->updated_at);
    }

    #[DataProvider('uniqueColumns')]
    public function test_rejects_duplicate_account_identifiers(string $column): void
    {
        $existing = User::factory()->create();

        $this->expectException(QueryException::class);

        User::factory()->create([$column => $existing->getAttribute($column)]);
    }

    public static function uniqueColumns(): array
    {
        return ['username' => ['username'], 'email' => ['email']];
    }

    #[DataProvider('requiredColumns')]
    public function test_rejects_null_required_account_values(string $column): void
    {
        $attributes = User::factory()->raw();
        $attributes[$column] = null;

        $this->expectException(QueryException::class);

        DB::table('users')->insert($attributes);
    }

    public static function requiredColumns(): array
    {
        return [
            'username' => ['username'], 'email' => ['email'],
            'password' => ['password'], 'status' => ['status'],
        ];
    }

    #[DataProvider('allowedStatuses')]
    public function test_persists_allowed_status_changes(string $status): void
    {
        $user = User::factory()->create();
        $user->status = $status;
        $user->save();

        $this->assertSame($status, $user->refresh()->status);
    }

    public static function allowedStatuses(): array
    {
        return [
            'active' => ['active'], 'suspended' => ['suspended'], 'closed' => ['closed'],
        ];
    }

    public function test_database_rejects_unknown_status(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('users')->where('id', $user->id)->update(['status' => 'pending']);
    }

    public function test_mass_assignment_accepts_account_credentials_but_cannot_change_status(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);
        $user->fill([
            'username' => 'updated_user',
            'email' => 'updated@example.com',
            'password' => 'new-secret-password',
            'status' => 'active',
        ])->save();
        $user->refresh();

        $this->assertSame('updated_user', $user->username);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertSame('suspended', $user->status);
        $this->assertTrue(Hash::check('new-secret-password', $user->password));
    }

    public function test_authentication_and_serialization_remain_compatible(): void
    {
        $user = User::factory()->create(['password' => 'account-secret'])->refresh();

        $this->assertTrue(Auth::attempt(['email' => $user->email, 'password' => 'account-secret'], true));
        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty($user->refresh()->getRememberToken());
        $this->assertInstanceOf(Carbon::class, $user->email_verified_at);
        $this->assertEqualsCanonicalizing([
            'id', 'username', 'email', 'email_verified_at', 'status', 'created_at', 'updated_at',
        ], array_keys($user->toArray()));
        Auth::logout();
        $this->assertFalse(Auth::attempt(['email' => $user->email, 'password' => 'wrong-password']));
    }

    public function test_default_seeder_creates_a_compatible_account(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', [
            'username' => 'testuser', 'email' => 'test@example.com', 'status' => 'active',
        ]);
    }
}
