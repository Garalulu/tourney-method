<?php

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $command = $this;
    /** @var Command $command */
    $command->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Discord notification schedules for tournament watching features.
|
*/

// Registration reminders: Check hourly for tournaments with registration closing within 24h
Schedule::command('reminders:registration')->hourly();

// In-app tournament notifications: registration opened / closing for eligible users
Schedule::command('notifications:eligible-tournaments')->hourly();

// Stream checks: Poll every 3 minutes for newly live streams
Schedule::command('streams:check')->everyThreeMinutes();

// Dashboard rank milestones: refresh PP values outside user requests
Schedule::command('rankings:refresh-pp')->dailyAt('08:30')->timezone('Asia/Seoul');

// Daily database backup & Tournament parser: Daily at 09:00 KST (00:00 UTC)
Schedule::command('tournaments:parse --confirm')->dailyAt('09:00')->timezone('Asia/Seoul');

// Podium user data sync, orphan cleanup, and storage pruning: Weekly on Mondays at 02:00 KST (Sunday 17:00 UTC)
Schedule::command('maintenance:weekly-essential')->weeklyOn(1, '02:00')->timezone('Asia/Seoul')
    ->onFailure(fn () => Log::error('Weekly essential podium user sync, tournament sync, and cleanup failed'))
    ->description('Sync essential podium user data, sync tournament badges, clean orphaned users, then prune database storage');
