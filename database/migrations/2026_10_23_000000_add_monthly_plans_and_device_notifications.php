<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1.7:
 *  - monthly repeating plans (installments, rent, cheques): the day of the
 *    month and the calendar the month is counted in (Persian months for a
 *    Persian-calendar user);
 *  - notifications on the person's devices (Web Push): one row per browser
 *    or phone that allowed it, and a marker for outbox rows already pushed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_schedule_rules', function (Blueprint $table): void {
            $table->unsignedTinyInteger('month_day')->nullable();
            $table->string('calendar', 24)->nullable();
        });

        Schema::table('notification_outbox', function (Blueprint $table): void {
            $table->dateTime('pushed_at')->nullable();
            $table->index(['pushed_at', 'dispatched_at'], 'notification_outbox_push_ix');
        });

        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique('push_subscriptions_endpoint_uq');
            $table->string('public_key', 200);
            $table->string('auth_token', 100);
            $table->string('user_agent', 300)->nullable();
            $table->unsignedSmallInteger('failures')->default(0);
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id', 'push_subscriptions_user_ix');
            $table->foreign('user_id', 'push_subscriptions_user_fk')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::table('notification_outbox', function (Blueprint $table): void {
            $table->dropIndex('notification_outbox_push_ix');
            $table->dropColumn('pushed_at');
        });
        Schema::table('plan_schedule_rules', function (Blueprint $table): void {
            $table->dropColumn(['month_day', 'calendar']);
        });
    }
};
