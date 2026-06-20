<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\InAppNotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class SendEligibleTournamentNotifications extends Command
{
    protected $signature = 'notifications:eligible-tournaments';

    protected $description = 'Create in-app notifications for eligible tournament registration alerts';

    public function handle(InAppNotificationService $notifications): int
    {
        $users = User::query()
            ->where('main_mode_source', 'oauth_setup')
            ->whereNotNull('main_mode')
            ->with('rankHistory')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No OAuth setup users found.');

            return Command::SUCCESS;
        }

        /** @var Collection<int, Tournament> $openTournaments */
        $openTournaments = Tournament::query()
            ->approved()
            ->whereNotNull('registration_start')
            ->whereNotNull('registration_end')
            ->where('registration_start', '<=', now())
            ->where('registration_end', '>', now())
            ->get();

        /** @var Collection<int, Tournament> $closingTournaments */
        $closingTournaments = Tournament::query()
            ->approved()
            ->whereNotNull('registration_end')
            ->whereBetween('registration_end', [now(), now()->addHours(24)])
            ->get();

        $created = 0;

        foreach ($users as $user) {
            foreach ($openTournaments as $tournament) {
                if ($tournament->matchesUserMode($user) && $tournament->isEligibleForUser($user)) {
                    $created += (int) $notifications->notifyEligibleTournament(
                        $user,
                        $tournament,
                        UserNotification::TYPE_ELIGIBLE_REGISTRATION_OPEN
                    );
                }
            }

            foreach ($closingTournaments as $tournament) {
                if ($tournament->matchesUserMode($user) && $tournament->isEligibleForUser($user)) {
                    $created += (int) $notifications->notifyEligibleTournament(
                        $user,
                        $tournament,
                        UserNotification::TYPE_ELIGIBLE_REGISTRATION_CLOSING
                    );
                }
            }
        }

        $this->info("Created {$created} eligible tournament notification(s).");

        return Command::SUCCESS;
    }
}
