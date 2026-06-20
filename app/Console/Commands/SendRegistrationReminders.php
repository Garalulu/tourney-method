<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationQueue;
use App\Models\Tournament;
use App\Models\TournamentWatch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendRegistrationReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reminders:registration';

    /**
     * The console command description.
     */
    protected $description = 'Send registration deadline reminders (24h before close) to watching users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for tournaments with registration closing within 24 hours...');

        // Find tournaments where registration ends within the next 24 hours
        $tournaments = Tournament::where('status', 'approved')
            ->whereNotNull('registration_end')
            ->whereBetween('registration_end', [now(), now()->addHours(24)])
            ->get();

        if ($tournaments->isEmpty()) {
            $this->info('No tournaments with registration closing soon.');

            return Command::SUCCESS;
        }

        $this->info("Found {$tournaments->count()} tournament(s) with registration closing soon.");

        $notificationCount = 0;
        $skippedCount = 0;

        foreach ($tournaments as $tournament) {
            $this->line("Processing: {$tournament->title}");

            // Get users watching this tournament with notify_registration_close enabled
            // AND who have a valid Discord webhook configured
            $watches = TournamentWatch::with('user')
                ->where('tournament_id', $tournament->id)
                ->where('notify_registration_close', true)
                ->whereHas('user', function ($query) {
                    $query->whereNotNull('discord_webhook_url')
                        ->where('discord_webhook_valid', true)
                        ->where('notify_registration', true);
                })
                ->get();

            foreach ($watches as $watch) {
                $user = $watch->user;

                // Check for deduplication: don't send if already sent for this tournament
                $existingNotification = NotificationQueue::where('user_id', $user->id)
                    ->where('tournament_id', $tournament->id)
                    ->where('type', 'registration_reminder')
                    ->whereIn('status', ['pending', 'sent'])
                    ->exists();

                if ($existingNotification) {
                    $skippedCount++;

                    continue;
                }

                // Get host user's avatar
                $hostUser = null;
                $hostAvatarUrl = null;
                if ($tournament->host_osu_id) {
                    $hostUser = User::where('osu_id', $tournament->host_osu_id)->first();
                    $hostAvatarUrl = $hostUser?->avatar_url;
                }

                // Create notification queue entry
                $notification = NotificationQueue::create([
                    'user_id' => $user->id,
                    'tournament_id' => $tournament->id,
                    'type' => 'registration_reminder',
                    'channel' => 'discord_personal',
                    'payload' => [
                        'title' => $tournament->title,
                        'banner_url' => $tournament->banner_url,
                        'modes' => $tournament->modes,
                        'rank_range_min' => $tournament->rank_range_min,
                        'rank_range_max' => $tournament->rank_range_max,
                        'registration_end' => $tournament->registration_end?->timestamp,
                        'registration_url' => $tournament->registration_url,
                        'team_size_display' => $tournament->team_size_display,
                        'vs_size' => $tournament->vs_size,
                        'team_size_min' => $tournament->team_size_min,
                        'team_size_max' => $tournament->team_size_max,
                        'host_username' => $tournament->host_username,
                        'host_avatar' => $hostAvatarUrl,
                    ],
                    'status' => 'pending',
                    'scheduled_for' => now(),
                ]);

                // Dispatch job
                SendNotificationJob::dispatch($notification->id);
                $notificationCount++;
            }
        }

        $this->info("Queued {$notificationCount} notification(s), skipped {$skippedCount} (already sent).");

        Log::info('Registration reminders processed', [
            'tournaments' => $tournaments->count(),
            'notifications_queued' => $notificationCount,
            'skipped' => $skippedCount,
        ]);

        return Command::SUCCESS;
    }
}
