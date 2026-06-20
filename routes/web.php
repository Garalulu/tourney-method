<?php

use App\Http\Controllers\Admin\AdminAsyncOperationController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DiscordDestinationController;
use App\Http\Controllers\Admin\DiscordServerController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\ParticipationModerationController;
use App\Http\Controllers\Admin\TournamentController as AdminTournamentController;
use App\Http\Controllers\Admin\TournamentCorrectionController as AdminTournamentCorrectionController;
use App\Http\Controllers\Admin\TournamentLifecycleController as AdminTournamentLifecycleController;
use App\Http\Controllers\Admin\TournamentParsingController as AdminTournamentParsingController;
use App\Http\Controllers\Admin\TournamentPodiumController as AdminTournamentPodiumController;
use App\Http\Controllers\Admin\TournamentStaffController as AdminTournamentStaffController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentCorrectionController;
use App\Http\Controllers\TournamentWatchController;
use App\Http\Controllers\UserParticipationController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\UserSetupController;
use App\Http\Controllers\YearRecapController;
use Illuminate\Support\Facades\Route;

// Health check endpoint for Railway monitoring
Route::get('/health', HealthCheckController::class)->name('health');

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::view('/contribute', 'contribute')->name('contribute');

// Language switching
Route::post('/language-switch', [LanguageController::class, 'switch'])->name('language.switch');

// Tournament discovery (public)
Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
Route::middleware(['auth', 'user.setup'])->group(function () {
    Route::get('/tournaments/add', [TournamentCorrectionController::class, 'createTournament'])->name('tournaments.add');
    Route::post('/tournaments/add/check-duplicates', [TournamentCorrectionController::class, 'checkDuplicates'])->name('tournaments.add.check-duplicates');
    Route::post('/tournaments/add', [TournamentCorrectionController::class, 'storeTournament'])
        ->middleware('throttle:tournament_corrections')
        ->name('tournaments.add.store');
});
Route::get('/tournaments/{tournament}/results-expanded', [TournamentController::class, 'expandedResults'])->name('tournaments.results-expanded');
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');

// Public user profiles (per spec.md FR-039)
// Add number constraint following OpenAPI userId type (integer)
Route::get('/users/{user}', [UserProfileController::class, 'show'])->whereNumber('user')->name('users.show');

// Public user match statistics (per OpenAPI: GET /users/{userId}/stats)
Route::get('/users/{user}/stats', [UserProfileController::class, 'stats'])->whereNumber('user')->name('users.stats');

Route::get('/users/{user}/participation/records', [UserParticipationController::class, 'records'])
    ->whereNumber('user')
    ->name('users.participation.records');
Route::get('/users/{user}/contributions', [UserProfileController::class, 'contributions'])
    ->whereNumber('user')
    ->name('users.contributions');

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::get('/callback', [AuthController::class, 'callback']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me']); // Returns 401 JSON if unauthenticated
    Route::post('/sync', [AuthController::class, 'sync'])->middleware('throttle:user_sync'); // FR-006: Once per 24 hours
});

// User search API (for admin staff management)
Route::middleware(['auth', 'role:admin,master'])->group(function () {
    Route::get('/api/users/search', [AdminUserController::class, 'search'])->name('api.users.search');
    Route::get('/api/users/{username}/sync', [AdminUserController::class, 'sync'])->name('api.users.sync');
});

// Protected routes
Route::middleware(['auth'])->group(function () {
    Route::get('/corrections/{correction}', [TournamentCorrectionController::class, 'show'])
        ->name('tournament-corrections.show');
    Route::get('/corrections/{correction}/status', [TournamentCorrectionController::class, 'status'])
        ->name('tournament-corrections.status');
    Route::post('/corrections/{correction}/comments', [TournamentCorrectionController::class, 'storeComment'])
        ->middleware('throttle:10,1')
        ->name('tournament-corrections.comments.store');

    // User setup (required after first login)
    Route::get('/users/setup', [UserSetupController::class, 'show'])->name('setup.show');
    Route::post('/users/setup', [UserSetupController::class, 'store'])->name('setup.store');

    // Main application routes (requires setup completion)
    Route::middleware(['user.setup'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Tournament watch routes (per OpenAPI: POST/DELETE /tournaments/{tournamentId}/watch)
        Route::post('/tournaments/{tournament}/watch', [TournamentWatchController::class, 'store'])->name('tournaments.watch');
        Route::delete('/tournaments/{tournament}/watch', [TournamentWatchController::class, 'destroy'])->name('tournaments.unwatch');
        Route::get('/tournaments/{tournament}/corrections/create', [TournamentCorrectionController::class, 'create'])->name('tournaments.corrections.create');
        Route::post('/tournaments/{tournament}/corrections', [TournamentCorrectionController::class, 'store'])
            ->middleware('throttle:tournament_corrections')
            ->name('tournaments.corrections.store');
        Route::get('/tournaments/{tournament}/corrections/history', [TournamentCorrectionController::class, 'history'])->name('tournaments.corrections.history');

        // User settings routes (per OpenAPI: GET/PATCH /users/me/settings)
        Route::get('/users/me/settings', [UserSettingsController::class, 'index'])->name('settings.index');
        Route::patch('/users/me/settings', [UserSettingsController::class, 'update'])->name('settings.update');

        // Webhook test route (per OpenAPI: POST /notifications/webhook/test)
        Route::post('/notifications/webhook/test', [UserSettingsController::class, 'testWebhook'])->name('webhook.test');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::delete('/notifications/read', [NotificationController::class, 'destroyRead'])->name('notifications.destroy-read');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

        Route::post('/users/{user}/participation', [UserParticipationController::class, 'store'])
            ->middleware('throttle:participation_writes')
            ->whereNumber('user')
            ->name('users.participation.store');
        Route::get('/users/{user}/participation/tournaments/search', [UserParticipationController::class, 'searchTournaments'])
            ->middleware('throttle:participation_search')
            ->whereNumber('user')
            ->name('users.participation.tournaments.search');
        Route::get('/users/{user}/participation/users/search', [UserParticipationController::class, 'searchUsers'])
            ->middleware('throttle:participation_search')
            ->whereNumber('user')
            ->name('users.participation.users.search');
        Route::get('/users/{user}/participation/lobbies/search', [UserParticipationController::class, 'searchLobbies'])
            ->middleware('throttle:participation_lobby_search')
            ->whereNumber('user')
            ->name('users.participation.lobbies.search');
        Route::patch('/users/{user}/participation/{record}', [UserParticipationController::class, 'update'])
            ->middleware('throttle:participation_writes')
            ->whereNumber('user')
            ->name('users.participation.update');
        Route::patch('/users/{user}/participation/{record}/visibility', [UserParticipationController::class, 'toggleVisibility'])
            ->middleware('throttle:participation_writes')
            ->whereNumber('user')
            ->name('users.participation.visibility');
        Route::post('/users/{user}/participation/{record}/deletion-request', [UserParticipationController::class, 'requestDeletion'])
            ->middleware('throttle:participation_writes')
            ->whereNumber('user')
            ->name('users.participation.deletion-request');
        Route::delete('/users/{user}/participation/{record}/deletion-request', [UserParticipationController::class, 'cancelDeletionRequest'])
            ->middleware('throttle:participation_writes')
            ->whereNumber('user')
            ->name('users.participation.deletion-request.cancel');
        Route::post('/users/{user}/participation/{record}/report', [UserParticipationController::class, 'report'])
            ->middleware('throttle:participation_writes')
            ->whereNumber('user')
            ->name('users.participation.report');
        Route::delete('/users/{user}/participation/{record}', [UserParticipationController::class, 'destroy'])
            ->middleware('throttle:participation_writes')
            ->whereNumber('user')
            ->name('users.participation.destroy');

        // Staff role submission - REMOVED: Staff roles are now managed directly by admins in tournament review panel
        // Route::post('/users/me/staff', [StaffRoleController::class, 'store'])->name('staff.store');

        // Year recap generation (per OpenAPI: POST /users/me/recap/{year}, GET /users/me/recap/{year}/download)
        Route::prefix('users/me/recap')->name('recap.')->group(function () {
            Route::post('/{year}', [YearRecapController::class, 'generate'])->name('generate');
            Route::get('/{year}/download', [YearRecapController::class, 'download'])->name('download');
        });
    });
});

// Admin routes - outside auth middleware, role middleware handles auth and returns JSON (401/403)
Route::middleware(['role:admin,master'])->prefix('admin')->name('admin.')->group(function () {
    // Admin dashboard
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/operations/{operation}', [AdminAsyncOperationController::class, 'show'])->name('operations.show');

    Route::post('/discord-destinations/{discordDestination}/test-send', [DiscordDestinationController::class, 'testSend'])
        ->name('discord-destinations.test-send');
    Route::redirect('/discord-destinations', '/admin/discord-servers');
    Route::resource('discord-servers', DiscordServerController::class)
        ->except(['show']);

    // Tournament management
    Route::get('/tournaments/pending', [AdminTournamentController::class, 'pending'])->name('tournaments.pending');
    Route::get('/tournaments/create', [AdminTournamentController::class, 'create'])->name('tournaments.create');
    Route::post('/tournaments', [AdminTournamentController::class, 'store'])->name('tournaments.store');
    Route::get('/tournaments/{tournament}', [AdminTournamentController::class, 'show'])->name('tournaments.show');
    Route::get('/tournaments/{tournament}/parse-history', [AdminTournamentParsingController::class, 'parseHistory'])->name('tournaments.parse-history');
    Route::get('/tournaments/{tournament}/parse-history/{history}', [AdminTournamentParsingController::class, 'showParseHistory'])->name('tournaments.parse-history.show');
    Route::post('/tournaments/{tournament}/reparse', [AdminTournamentParsingController::class, 'reparse'])->name('tournaments.reparse');
    Route::patch('/tournaments/{tournament}', [AdminTournamentController::class, 'update'])->name('tournaments.update');
    Route::post('/tournaments/{tournament}/approve', [AdminTournamentLifecycleController::class, 'approve'])->name('tournaments.approve');
    Route::post('/tournaments/{tournament}/reject', [AdminTournamentLifecycleController::class, 'reject'])->name('tournaments.reject');
    Route::post('/tournaments/{tournament}/copy', [AdminTournamentLifecycleController::class, 'copy'])->name('tournaments.copy');
    Route::post('/tournaments/{tournament}/refresh-banner', [AdminTournamentController::class, 'refreshBanner'])->name('tournaments.refresh-banner');
    Route::post('/tournaments/{tournament}/restore', [AdminTournamentLifecycleController::class, 'restore'])->name('tournaments.restore');
    Route::post('/tournaments/{tournament}/make-host/{user}', [AdminTournamentStaffController::class, 'makeHost'])->name('tournaments.makeHost');
    Route::delete('/tournaments/{tournament}', [AdminTournamentLifecycleController::class, 'destroy'])->name('tournaments.destroy');
    Route::delete('/tournaments/{tournament}/parse-history/{history}', [AdminTournamentParsingController::class, 'deleteParseHistory'])->name('tournaments.parse-history.delete');

    // Tournament staff management (admin workflow)
    Route::prefix('tournaments/{tournament}/staff')->name('tournaments.staff.')->group(function () {
        Route::get('/', [AdminTournamentStaffController::class, 'fetchUserRoles'])->name('fetch');
        Route::get('/component', [AdminTournamentStaffController::class, 'getStaffComponent'])->name('component');
        Route::post('/', [AdminTournamentStaffController::class, 'addStaff'])->name('add');
        Route::patch('/users/{user}/roles', [AdminTournamentStaffController::class, 'replaceStaffRoles'])->name('roles.replace');
        Route::patch('/{staff}', [AdminTournamentStaffController::class, 'updateStaff'])->name('update');
        Route::delete('/roles/{role}', [AdminTournamentStaffController::class, 'removeStaffRole'])->name('roles.remove');
        Route::delete('/{user}', [AdminTournamentStaffController::class, 'removeStaff'])->name('remove');
    });

    // Tournament podium management (admin workflow)
    Route::prefix('tournaments/{tournament}/podium')->name('tournaments.podium.')->group(function () {
        Route::get('/component', [AdminTournamentPodiumController::class, 'getPodiumComponent'])->name('component');
        Route::post('/', [AdminTournamentPodiumController::class, 'storePodium'])->name('store');
        Route::post('/groups', [AdminTournamentPodiumController::class, 'groupPodiumWinners'])->name('groups.store');
        Route::post('/groups/new', [AdminTournamentPodiumController::class, 'splitPodiumWinnersToNewGroup'])->name('groups.new');
        Route::delete('/{winner}/group', [AdminTournamentPodiumController::class, 'splitPodiumWinner'])->name('groups.split');
        Route::patch('/{winner}', [AdminTournamentPodiumController::class, 'updatePodium'])->name('update');
        Route::delete('/{winner}', [AdminTournamentPodiumController::class, 'destroyPodium'])->name('destroy');
    });

    // Tournament badge management (admin workflow)
    Route::prefix('tournaments/{tournament}/badges')->name('tournaments.badges.')->group(function () {
        Route::post('/', [AdminTournamentPodiumController::class, 'addBadgeUrl'])->name('add');
        Route::delete('/', [AdminTournamentPodiumController::class, 'removeBadgeUrl'])->name('remove');
    });

    // Tournament conflict resolution (admin workflow)
    Route::prefix('tournaments/{tournament}')->name('tournaments.')->group(function () {
        Route::get('/conflicts', [AdminTournamentParsingController::class, 'previewConflicts'])->name('conflicts.preview');
        Route::post('/conflicts/reparse', [AdminTournamentParsingController::class, 'reparseWithResolution'])->name('reparse.resolve');
    });

    // Audit log (per OpenAPI spec)
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

    Route::get('/participation-moderation', [ParticipationModerationController::class, 'index'])->name('participation-moderation.index');
    Route::post('/participation-moderation/records/{record}/delete-input', [ParticipationModerationController::class, 'deleteInput'])->name('participation-moderation.delete-input');
    Route::post('/participation-moderation/logs/{log}/rollback-teammates', [ParticipationModerationController::class, 'rollbackTeammates'])->name('participation-moderation.logs.rollback-teammates');
    Route::post('/participation-moderation/add-requests/{record}/approve', [ParticipationModerationController::class, 'approveAddRequest'])->name('participation-moderation.add-requests.approve');
    Route::post('/participation-moderation/add-requests/{record}/reject', [ParticipationModerationController::class, 'rejectAddRequest'])->name('participation-moderation.add-requests.reject');
    Route::post('/participation-moderation/deletion-requests/{deletionRequest}/approve', [ParticipationModerationController::class, 'approveDeletionRequest'])->name('participation-moderation.deletion-requests.approve');
    Route::post('/participation-moderation/deletion-requests/{deletionRequest}/reject', [ParticipationModerationController::class, 'rejectDeletionRequest'])->name('participation-moderation.deletion-requests.reject');
    Route::post('/participation-moderation/reports/{report}/resolve', [ParticipationModerationController::class, 'resolveReport'])->name('participation-moderation.reports.resolve');
    Route::post('/participation-moderation/users/{user}/lock', [ParticipationModerationController::class, 'lock'])->name('participation-moderation.lock');
    Route::delete('/participation-moderation/users/{user}/lock', [ParticipationModerationController::class, 'unlock'])->name('participation-moderation.unlock');

    Route::get('/tournament-corrections', [AdminTournamentCorrectionController::class, 'index'])->name('tournament-corrections.index');
    Route::get('/tournament-corrections/{correction}', [AdminTournamentCorrectionController::class, 'show'])->name('tournament-corrections.show');
    Route::post('/tournament-corrections/{correction}/review', [AdminTournamentCorrectionController::class, 'review'])->name('tournament-corrections.review');

    // Import job history (admin monitoring per FR-065)
    Route::prefix('imports')->name('imports.')->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::post('/manual', [ImportController::class, 'manualImport'])->name('manual');
    });

});

// Master-only routes (per OpenAPI spec: GET /admin/users, PATCH /admin/users/{userId}/role)
Route::middleware(['role:master'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.updateRole');
    Route::delete('/users', [AdminUserController::class, 'bulkDestroy'])->name('users.bulk-destroy');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/sync-selected', [AdminUserController::class, 'syncSelected'])->name('users.sync-selected');
    Route::post('/users/cleanup-orphans', [AdminUserController::class, 'cleanupOrphans'])->name('users.cleanup-orphans');
});

Route::get('/users/{username}', [UserProfileController::class, 'redirectByUsername'])
    ->name('users.redirect-by-username');
