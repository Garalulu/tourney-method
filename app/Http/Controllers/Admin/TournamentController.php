<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\StaffRoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectTournamentRequest;
use App\Http\Requests\Admin\TournamentStoreRequest;
use App\Http\Requests\Admin\TournamentUpdateRequest;
use App\Jobs\CacheTournamentBannerJob;
use App\Jobs\PostTournamentToDiscordJob;
use App\Jobs\ProcessBulkPodiumAddJob;
use App\Jobs\ProcessBulkStaffAddJob;
use App\Models\AdminAsyncOperation;
use App\Models\AdminAuditLog;
use App\Models\Tournament;
use App\Models\TournamentParseHistory;
use App\Models\TournamentStaff;
use App\Models\TournamentWinner;
use App\Models\User;
use App\Services\AdminTournamentFormService;
use App\Services\AdminTournamentParseService;
use App\Services\ParticipationPodiumBackfillService;
use App\Services\TournamentParticipantSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TournamentController extends Controller
{
    private const QUEUE_SORT_COLUMNS = [
        'registration_start' => 'registration_start',
        'tournament_end' => 'tournament_end',
        'updated_at' => 'updated_at',
    ];

    public function pending(Request $request): View
    {
        $tabs = [
            'pending' => [
                'label' => 'Pending',
                'status' => Tournament::STATUS_PENDING,
                'description' => 'Needs admin review',
            ],
            'approved' => [
                'label' => 'Approved',
                'status' => Tournament::STATUS_APPROVED,
                'description' => 'Visible to players',
            ],
            'rejected' => [
                'label' => 'Rejected',
                'status' => Tournament::STATUS_REJECTED,
                'description' => 'Returned or declined',
            ],
        ];

        $activeTab = $this->resolveTournamentQueueTab($request, array_keys($tabs));
        $page = $this->resolveTournamentQueuePage($request, $activeTab);
        $search = trim((string) $request->input('search', ''));
        $selectedModes = $this->resolveTournamentQueueModes($request->input('modes', []));
        $filterModes = $this->expandTournamentModeAliases($selectedModes);
        $badgeOnly = $request->boolean('badge');
        $updatedOnly = $activeTab === 'approved' && $request->boolean('updated');
        [$currentSort, $currentDirection] = $this->resolveTournamentQueueSort($request);

        $baseQuery = Tournament::query()
            ->search($search)
            ->filterModes($filterModes);

        if ($badgeOnly) {
            $baseQuery->badge();
        }

        $counts = collect($tabs)
            ->mapWithKeys(fn (array $tab, string $key) => [
                $key => $this->applyTournamentQueueStatusFilters(
                    (clone $baseQuery)->where('status', $tab['status']),
                    $key,
                    $updatedOnly
                )->count(),
            ])
            ->all();

        $tournamentQuery = (clone $baseQuery)
            ->where('status', $tabs[$activeTab]['status'])
            ->with('host');

        $this->applyTournamentQueueStatusFilters($tournamentQuery, $activeTab, $updatedOnly);
        $this->applyTournamentQueueSorting($tournamentQuery, $activeTab, $currentSort, $currentDirection);

        $paginationQuery = array_filter([
            'tab' => $activeTab,
            'search' => $search,
            'modes' => $selectedModes,
            'badge' => $badgeOnly ? '1' : null,
            'updated' => $updatedOnly ? '1' : null,
            'sort' => $currentSort,
            'direction' => $currentDirection,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        $tournaments = $tournamentQuery
            ->paginate(20, ['*'], 'page', $page)
            ->appends($paginationQuery);

        $modeOptions = [
            'osu' => 'osu!',
            'taiko' => 'osu!taiko',
            'catch' => 'osu!catch',
            'mania' => 'osu!mania',
        ];

        return view('admin.tournaments.pending', compact(
            'activeTab',
            'badgeOnly',
            'counts',
            'currentDirection',
            'currentSort',
            'modeOptions',
            'search',
            'selectedModes',
            'tabs',
            'tournaments',
            'updatedOnly',
        ));
    }

    /**
     * @param  array<int, string>  $allowedTabs
     */
    private function resolveTournamentQueueTab(Request $request, array $allowedTabs): string
    {
        $tab = (string) $request->input('tab', '');

        if (in_array($tab, $allowedTabs, true)) {
            return $tab;
        }

        if ($request->has('approved_page')) {
            return 'approved';
        }

        if ($request->has('rejected_page')) {
            return 'rejected';
        }

        return 'pending';
    }

    private function resolveTournamentQueuePage(Request $request, string $activeTab): int
    {
        $legacyPageName = "{$activeTab}_page";
        $page = $request->input($legacyPageName, $request->input('page', 1));

        return max(1, (int) $page);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveTournamentQueueSort(Request $request): array
    {
        $sort = (string) $request->query('sort', '');
        $direction = (string) $request->query('direction', '');

        if ($sort === '' && $direction === '') {
            return [null, null];
        }

        if (! array_key_exists($sort, self::QUEUE_SORT_COLUMNS)) {
            return [null, null];
        }

        if ($direction !== '' && ! in_array($direction, ['asc', 'desc'], true)) {
            return [null, null];
        }

        return [$sort, $direction !== '' ? $direction : 'asc'];
    }

    /**
     * @param  Builder<Tournament>  $query
     */
    private function applyTournamentQueueSorting(Builder $query, string $activeTab, ?string $sort, ?string $direction): void
    {
        if ($sort !== null && $direction !== null) {
            $column = self::QUEUE_SORT_COLUMNS[$sort];

            if ($column === 'updated_at') {
                $query->orderBy($column, $direction)
                    ->orderByDesc('id');

                return;
            }

            $query->orderByRaw("{$column} IS NULL ".($direction === 'asc' ? 'DESC' : 'ASC'))
                ->orderBy($column, $direction)
                ->orderByDesc('id');

            return;
        }

        if ($activeTab === 'pending') {
            $query->orderByRaw('registration_start IS NULL DESC')
                ->orderBy('registration_start')
                ->orderByDesc('id');

            return;
        }

        $query->latestEditedFirst();
    }

    /**
     * @param  Builder<Tournament>  $query
     * @return Builder<Tournament>
     */
    private function applyTournamentQueueStatusFilters(Builder $query, string $tab, bool $updatedOnly): Builder
    {
        if ($tab === 'approved' && $updatedOnly) {
            $query->unread();
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    private function resolveTournamentQueueModes(mixed $modes): array
    {
        $modes = is_array($modes) ? $modes : [$modes];
        $allowedModes = ['osu', 'taiko', 'catch', 'fruits', 'mania'];

        $normalized = collect($modes)
            ->map(fn ($mode) => strtolower((string) $mode))
            ->filter(fn ($mode) => in_array($mode, $allowedModes, true))
            ->map(fn ($mode) => $mode === 'fruits' ? 'catch' : $mode)
            ->unique()
            ->values()
            ->all();

        return $normalized;
    }

    /**
     * @param  array<int, string>  $modes
     * @return array<int, string>
     */
    private function expandTournamentModeAliases(array $modes): array
    {
        $expanded = [];

        foreach ($modes as $mode) {
            $expanded[] = $mode;

            if ($mode === 'catch') {
                $expanded[] = 'fruits';
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Show the form for creating a new tournament
     *
     * GET /admin/tournaments/create
     */
    public function create(): View
    {
        $tournament = new Tournament([
            'status' => 'pending_review',
        ]);

        return view('admin.tournaments.create', compact('tournament'));
    }

    /**
     * Store a newly created tournament in storage
     *
     * POST /admin/tournaments
     */
    public function store(
        TournamentStoreRequest $request,
        AdminTournamentFormService $formService,
        AdminTournamentParseService $parseService
    ): RedirectResponse {
        $validated = $formService->normalizeForumLink($request->validated());
        $fieldSources = $formService->manualFieldSources($validated);

        $tournament = Tournament::create(array_merge($validated, [
            'status' => 'pending_review',
            'parsed_at' => now(),
            'field_sources' => $fieldSources,
            'import_source' => 'manual',
        ]));

        AdminAuditLog::log(
            $request->user(),
            'tournament.created',
            $tournament,
            ['data' => $validated]
        );

        // Automatically parse if forum_topic_id is provided
        if ($tournament->forum_topic_id) {
            $transactionId = $parseService->queueFullParse($tournament, 'creation');

            return redirect()->route('admin.tournaments.show', $tournament)
                ->with('success', 'Tournament created and queued for parsing. Transaction ID: '.$transactionId);
        }

        return redirect()->route('admin.tournaments.show', $tournament)
            ->with('success', 'Tournament created successfully');
    }

    public function show(Tournament $tournament): View
    {
        // Mark as viewed when admin opens the page
        if ($tournament->isUnread()) {
            $tournament->markAsViewed();
        }

        // Load tournament with staff sorted by role priority
        $tournament = Tournament::withStaffSortedByRole()->find($tournament->id);

        return view('admin.tournaments.review', compact('tournament'));
    }

    public function update(
        TournamentUpdateRequest $request,
        Tournament $tournament,
        AdminTournamentFormService $formService
    ): JsonResponse {
        $validated = $formService->normalizeForumLink($request->validated());
        $changes = $this->auditChangesForTournamentUpdate($tournament, $validated);

        // Check if banner_url is changing before update
        $bannerChanged = isset($validated['banner_url']) &&
                         $validated['banner_url'] !== $tournament->getOriginal('banner_url');

        $currentFieldSources = $formService->mergeManualFieldSources($tournament->field_sources ?? [], $validated);

        // Merge field_sources into the update data
        $updateData = array_merge($validated, ['field_sources' => $currentFieldSources]);
        $tournament->update($updateData);

        // Dispatch banner caching job if banner_url changed
        if ($bannerChanged) {
            CacheTournamentBannerJob::dispatch($tournament);
        }

        AdminAuditLog::log(
            $request->user(),
            'tournament.updated', // Fixed: match OpenAPI spec
            $tournament,
            ['changes' => $changes]
        );

        // OpenAPI spec: API endpoints return JSON (200 OK)
        return response()->json([
            'message' => 'Tournament updated successfully',
        ], 200);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function auditChangesForTournamentUpdate(Tournament $tournament, array $validated): array
    {
        $changes = [];

        foreach ($validated as $field => $newValue) {
            $oldValue = $tournament->getAttribute($field);
            $probe = $tournament->replicate();
            $probe->setAttribute($field, $newValue);
            $castNewValue = $probe->getAttribute($field);

            if ($this->auditValuesAreEquivalent($oldValue, $castNewValue)) {
                continue;
            }

            $changes[$field] = [
                'old' => $this->auditValue($oldValue),
                'new' => $this->auditValue($castNewValue),
            ];
        }

        return $changes;
    }

    private function auditValuesAreEquivalent(mixed $oldValue, mixed $newValue): bool
    {
        return $this->auditValue($oldValue) === $this->auditValue($newValue);
    }

    private function auditValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            ksort($value);
        }

        return $value;
    }

    public function approve(Request $request, Tournament $tournament): JsonResponse
    {
        // Validate tournament is pending
        if ($tournament->status !== 'pending_review') {
            return response()->json([
                'message' => 'Tournament cannot be approved',
                'error' => 'Tournament is not pending review',
            ], 400);
        }

        $validated = $request->validate([
            'skip_discord_webhook' => 'sometimes|boolean',
        ]);

        $skipWebhook = $validated['skip_discord_webhook'] ?? false;

        /** @var User $user */
        $user = $request->user();
        $tournament->approve($user);

        AdminAuditLog::log(
            $user,
            'tournament.approved', // Fixed: match OpenAPI spec
            $tournament,
            ['approved_at' => now()]
        );

        // Dispatch job to post tournament announcement to central Discord
        // Only dispatch if admin didn't request to skip webhook
        if (! $skipWebhook) {
            dispatch(new PostTournamentToDiscordJob($tournament->id));
        } else {
            Log::info('Discord webhook skipped (admin requested)', [
                'tournament_id' => $tournament->id,
                'title' => $tournament->title,
                'admin_id' => $user->id,
            ]);
        }

        // OpenAPI spec: API endpoints return JSON (200 OK)
        return response()->json([
            'message' => 'Tournament approved successfully',
        ], 200);
    }

    public function copy(Request $request, Tournament $tournament): JsonResponse
    {
        if ($tournament->status !== Tournament::STATUS_APPROVED || ! $tournament->isEnded()) {
            return response()->json([
                'message' => 'Tournament cannot be copied',
                'error' => 'Only ended approved tournaments can be copied',
            ], 400);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:256',
        ]);

        /** @var User $user */
        $user = $request->user();

        $copy = DB::transaction(function () use ($tournament, $validated, $user): Tournament {
            $copiedTournament = $tournament->replicate([
                'id',
                'tcomm_id',
                'otr_id',
                'import_batch_id',
                'otr_deleted_at',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);

            $fieldSources = $copiedTournament->field_sources ?? [];
            unset($fieldSources['badge_status']);

            $copiedTournament->forceFill([
                'title' => $validated['title'],
                'status' => Tournament::STATUS_APPROVED,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'viewed_at' => null,
                'badge_status' => null,
                'badge_urls' => null,
                'tcomm_id' => null,
                'otr_id' => null,
                'import_batch_id' => null,
                'otr_deleted_at' => null,
                'field_sources' => $fieldSources,
            ]);

            $copiedTournament->save();

            /** @var Collection<int, TournamentStaff> $staffRows */
            $staffRows = TournamentStaff::where('tournament_id', $tournament->id)
                ->orderBy('id')
                ->get();

            foreach ($staffRows as $staff) {
                TournamentStaff::query()->create([
                    'tournament_id' => $copiedTournament->id,
                    'user_id' => $staff->user_id,
                    'role' => $staff->role,
                    'notes' => $staff->notes,
                    'status' => $staff->status,
                    'submitted_at' => $staff->submitted_at,
                    'reviewed_at' => $staff->reviewed_at,
                    'reviewed_by' => $staff->reviewed_by,
                    'source' => $staff->source,
                ]);
            }

            AdminAuditLog::log(
                $user,
                'tournament.copied',
                $copiedTournament,
                [
                    'source_tournament_id' => $tournament->id,
                    'copied_tournament_id' => $copiedTournament->id,
                ]
            );

            return $copiedTournament;
        });

        return response()->json([
            'message' => 'Tournament copied successfully',
            'tournament_id' => $copy->id,
            'url' => route('admin.tournaments.show', $copy),
        ], 201);
    }

    public function reject(RejectTournamentRequest $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validated();
        $reason = $validated['reason'] ?? 'No reason provided';

        // Validate tournament is pending
        if ($tournament->status !== 'pending_review') {
            return response()->json([
                'message' => 'Tournament cannot be rejected',
                'error' => 'Tournament is not pending review',
            ], 400);
        }

        /** @var User $user */
        $user = $request->user();

        $tournament->reject($reason, $user);

        AdminAuditLog::log(
            $user,
            'tournament.rejected',
            $tournament,
            ['rejection_reason' => $reason]
        );

        return response()->json([
            'message' => 'Tournament rejected',
        ], 200);
    }

    /**
     * Refresh tournament banner cache
     *
     * POST /admin/tournaments/{tournament}/refresh-banner
     */
    public function refreshBanner(Request $request, Tournament $tournament): JsonResponse
    {
        // Check if tournament has a banner URL
        if (! $tournament->banner_url) {
            return response()->json([
                'success' => false,
                'message' => 'Tournament does not have a banner URL',
            ], 400);
        }

        // Dispatch banner caching job
        CacheTournamentBannerJob::dispatch($tournament);

        AdminAuditLog::log(
            $request->user(),
            'tournament.banner_refreshed',
            $tournament,
            ['banner_url' => $tournament->banner_url]
        );

        return response()->json([
            'success' => true,
            'message' => 'Banner cache refresh queued successfully',
        ], 200);
    }

    /**
     * Add staff to tournament
     * Supports both registered users and unregistered osu! usernames
     *
     * POST /admin/tournaments/{tournament}/staff
     */
    public function addStaff(Request $request, Tournament $tournament): JsonResponse
    {
        // Get available roles dynamically from StaffRoleHelper
        $availableRoles = array_keys(StaffRoleHelper::getAvailableRoles());

        // Support both formats:
        // 1. Bulk add: usernames (string) + role (string)
        // 2. Individual add: user_id + roles (array)
        if ($request->has('usernames') && $request->has('role')) {
            // Bulk add format
            $validated = $request->validate([
                'usernames' => 'required|string',
                'role' => 'required|string|in:'.implode(',', $availableRoles),
            ]);

            $usernames = explode(',', $validated['usernames']);
            $usernames = array_map('trim', $usernames);
            $usernames = array_filter($usernames);
            $role = $validated['role'];

            if (empty($usernames)) {
                return response()->json([
                    'error' => 'No usernames provided',
                ], 400);
            }

            $operation = AdminAsyncOperation::query()->create([
                'type' => AdminAsyncOperation::TYPE_STAFF_BULK_ADD,
                'tournament_id' => $tournament->id,
                'status' => AdminAsyncOperation::STATUS_PENDING,
                'total' => count($usernames),
                'message' => 'Queued staff bulk add',
                'errors' => [],
                'result' => [],
            ]);

            ProcessBulkStaffAddJob::dispatch(
                $operation->id,
                $tournament->id,
                array_values($usernames),
                $role,
                (int) auth()->id()
            );

            return response()->json([
                'success' => true,
                'operation_id' => $operation->id,
                'message' => 'Staff bulk add queued',
            ], 202);
        }

        // Individual add format (original)
        $validated = $request->validate([
            'user_id' => 'required', // Can be "new:username" or actual user ID
            'roles' => 'required|array|min:1',
            'roles.*' => 'required|string|in:'.implode(',', $availableRoles),
        ]);

        $validated['roles'] = StaffRoleHelper::sortRoles($validated['roles']);

        $userId = $validated['user_id'];

        // Handle unregistered osu! user
        if (str_starts_with($userId, 'new:')) {
            $osuUsername = substr($userId, 4);
            try {
                $user = app(TournamentParticipantSyncService::class)->resolveStaffUserByUsername($osuUsername);
                $userId = $user->id;
            } catch (\RuntimeException $e) {
                return response()->json([
                    'error' => $e->getMessage(),
                ], 404);
            }
        }

        /** @var Collection<int, TournamentStaff> $createdStaff */
        $createdStaff = collect();

        // Create one TournamentStaff record per role
        foreach ($validated['roles'] as $role) {
            // Check if staff already exists for this tournament with this role
            $existing = TournamentStaff::where('tournament_id', $tournament->id)
                ->where('user_id', $userId)
                ->where('role', $role)
                ->first();

            if ($existing) {
                // Skip duplicates gracefully
                continue;
            }

            // Create staff record with approved status (added by admin)
            $staff = TournamentStaff::create([
                'tournament_id' => $tournament->id,
                'user_id' => $userId,
                'role' => $role,
                'status' => 'approved',
                'source' => 'manual', // Explicitly set source for re-parse protection
                'submitted_at' => now(),
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
            ]);

            // Load user relationship for response
            $staff->load('user');
            $createdStaff->push($staff);
        }

        // Log audit trail
        AdminAuditLog::log(
            auth()->user(),
            'tournament.staff_added',
            $tournament,
            [
                'user_id' => $userId,
                'roles' => $validated['roles'],
                'was_pre_registered' => str_starts_with($validated['user_id'], 'new:'),
            ]
        );

        return response()->json([
            'staff' => $createdStaff->map(fn (TournamentStaff $s) => [
                'id' => $s->id,
                'user' => [
                    'id' => $s->user->id,
                    'username' => $s->user->username,
                    'osu_id' => $s->user->osu_id,
                    'avatar_url' => $s->user->avatar_url,
                ],
                'role' => $s->role,
            ])->toArray(),
        ], 201);
    }

    /**
     * Update tournament staff role
     *
     * PATCH /admin/tournaments/{tournament}/staff/{staff}
     */
    public function updateStaff(Request $request, Tournament $tournament, TournamentStaff $staff): JsonResponse
    {
        // Verify staff belongs to this tournament
        if ($staff->tournament_id !== $tournament->id) {
            return response()->json([
                'error' => 'Staff not found for this tournament',
            ], 404);
        }

        // Get available roles dynamically from StaffRoleHelper
        $availableRoles = array_keys(StaffRoleHelper::getAvailableRoles());

        $validated = $request->validate([
            'role' => 'required|string|in:'.implode(',', $availableRoles),
        ]);

        $oldRole = $staff->role;
        $staff->update(['role' => $validated['role']]);

        AdminAuditLog::log(
            auth()->user(),
            'tournament.staff_role_updated',
            $tournament,
            [
                'staff_id' => $staff->id,
                'user_id' => $staff->user_id,
                'old_role' => $oldRole,
                'new_role' => $staff->role,
            ]
        );

        return response()->json([
            'success' => true,
            'role' => $staff->role,
        ]);
    }

    /**
     * Replace all tournament staff roles for a user in one action.
     *
     * PATCH /admin/tournaments/{tournament}/staff/users/{user}/roles
     */
    public function replaceStaffRoles(Request $request, Tournament $tournament, User $user): JsonResponse
    {
        $availableRoles = array_keys(StaffRoleHelper::getAvailableRoles());

        $validated = $request->validate([
            'roles' => 'present|array',
            'roles.*' => 'required|string|distinct|in:'.implode(',', $availableRoles),
        ]);

        $desiredRoles = StaffRoleHelper::sortRoles($validated['roles']);

        $result = DB::transaction(function () use ($tournament, $user, $desiredRoles): array {
            /** @var Collection<int, TournamentStaff> $currentStaff */
            $currentStaff = TournamentStaff::query()
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->get();

            $currentRoles = StaffRoleHelper::sortRoles($currentStaff->pluck('role')->all());
            $rolesToAdd = StaffRoleHelper::sortRoles(array_diff($desiredRoles, $currentRoles));
            $rolesToRemove = StaffRoleHelper::sortRoles(array_diff($currentRoles, $desiredRoles));
            $rolesToKeep = StaffRoleHelper::sortRoles(array_intersect($currentRoles, $desiredRoles));

            if ($rolesToRemove !== []) {
                TournamentStaff::query()
                    ->where('tournament_id', $tournament->id)
                    ->where('user_id', $user->id)
                    ->whereIn('role', $rolesToRemove)
                    ->delete();
            }

            foreach ($rolesToAdd as $role) {
                TournamentStaff::query()->create([
                    'tournament_id' => $tournament->id,
                    'user_id' => $user->id,
                    'role' => $role,
                    'status' => 'approved',
                    'source' => 'manual',
                    'submitted_at' => now(),
                    'reviewed_at' => now(),
                    'reviewed_by' => auth()->id(),
                ]);
            }

            return [
                'added' => $rolesToAdd,
                'removed' => $rolesToRemove,
                'kept' => $rolesToKeep,
            ];
        });

        AdminAuditLog::log(
            auth()->user(),
            'tournament.staff_roles_replaced',
            $tournament,
            [
                'user_id' => $user->id,
                'added' => $result['added'],
                'removed' => $result['removed'],
                'kept' => $result['kept'],
            ]
        );

        return response()->json([
            'success' => true,
            'added' => $result['added'],
            'removed' => $result['removed'],
            'kept' => $result['kept'],
        ]);
    }

    /**
     * Remove staff role from tournament user
     *
     * DELETE /admin/tournaments/{tournament}/staff/{user}
     *
     * @param  int  $userId  User ID
     */
    public function removeStaff(Request $request, Tournament $tournament, int $userId): JsonResponse
    {
        $availableRoles = array_keys(StaffRoleHelper::getAvailableRoles());
        $request->validate([
            'role' => 'required|string|in:'.implode(',', $availableRoles),
        ]);

        $staff = TournamentStaff::where('tournament_id', $tournament->id)
            ->where('user_id', $userId)
            ->where('role', $request->role)
            ->firstOrFail();

        $staff->delete();

        AdminAuditLog::log(
            auth()->user(),
            'tournament.staff_removed',
            $tournament,
            [
                'staff_id' => $staff->id,
                'user_id' => $staff->user_id,
                'role' => $staff->role,
            ]
        );

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Remove every staff assignment for one role from this tournament.
     *
     * DELETE /admin/tournaments/{tournament}/staff/roles/{role}
     */
    public function removeStaffRole(Request $request, Tournament $tournament, string $role): JsonResponse
    {
        $availableRoles = array_keys(StaffRoleHelper::getAvailableRoles());

        if (! in_array($role, $availableRoles, true)) {
            return response()->json([
                'message' => 'The selected role is invalid.',
                'errors' => [
                    'role' => ['The selected role is invalid.'],
                ],
            ], 422);
        }

        $deletedCount = TournamentStaff::query()
            ->where('tournament_id', $tournament->id)
            ->where('role', $role)
            ->delete();

        AdminAuditLog::log(
            $request->user(),
            'tournament.staff_role_bulk_removed',
            $tournament,
            [
                'role' => $role,
                'deleted_count' => $deletedCount,
            ]
        );

        return response()->json([
            'success' => true,
            'deleted_count' => $deletedCount,
            'role' => $role,
        ]);
    }

    /**
     * Fetch user's existing roles for tournament
     * Used by staff modal to prevent duplicate role assignments
     *
     * GET /admin/tournaments/{tournament}/staff?user_id={id}
     */
    public function fetchUserRoles(Request $request, Tournament $tournament): JsonResponse
    {
        // Get user_id from request
        $userId = $request->input('user_id');

        // Early return ONLY for truly malformed values that would cause SQL errors
        // These are edge cases that should be handled gracefully
        // Numeric values (even invalid ones like -1, 0, 9999999999) should pass through to validation
        if ($userId === null
            || $userId === 'null'
            || $userId === 'undefined'
            || (is_string($userId) && ! is_numeric($userId))
        ) {
            return response()->json([
                'staff' => [],
            ], 200);
        }

        // Cast to integer for safety
        // This handles string numeric values like "123" or "-1"
        $userId = (int) $userId;

        // Validate with Laravel rules
        // This will catch: negative numbers, zero, non-existent user IDs
        $validated = $request->validate([
            'user_id' => 'required|integer|min:1|exists:users,id',
        ]);

        // Fetch all staff records for this user and tournament
        $staff = TournamentStaff::where('tournament_id', $tournament->id)
            ->where('user_id', $validated['user_id'])
            ->get(['id', 'role', 'source'])
            ->sortBy(fn (TournamentStaff $staff): int => StaffRoleHelper::getRolePriority($staff->role))
            ->values();

        return response()->json([
            'staff' => $staff->map(fn (TournamentStaff $s) => [
                'id' => $s->id,
                'role' => $s->role,
                'source' => $s->source,
            ])->toArray(),
        ], 200);
    }

    /**
     * Store podium winners for a tournament
     *
     * POST /admin/tournaments/{tournament}/podium
     */
    public function storePodium(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'placement' => 'required|integer|min:1|max:3',
            'usernames' => 'required|string',
        ]);

        $placement = $validated['placement'];
        $usernames = explode(',', $validated['usernames']);
        $usernames = array_map('trim', $usernames);
        $usernames = array_filter($usernames);

        if (empty($usernames)) {
            return response()->json([
                'error' => 'No usernames provided',
            ], 400);
        }

        $operation = AdminAsyncOperation::query()->create([
            'type' => AdminAsyncOperation::TYPE_PODIUM_BULK_ADD,
            'tournament_id' => $tournament->id,
            'status' => AdminAsyncOperation::STATUS_PENDING,
            'total' => count($usernames),
            'message' => 'Queued podium bulk add',
            'errors' => [],
            'result' => [],
        ]);

        ProcessBulkPodiumAddJob::dispatch(
            $operation->id,
            $tournament->id,
            array_values($usernames),
            $placement,
            (int) auth()->id()
        );

        return response()->json([
            'success' => true,
            'operation_id' => $operation->id,
            'message' => 'Podium bulk add queued',
        ], 202);
    }

    /**
     * Update a podium winner
     *
     * PATCH /admin/tournaments/{tournament}/podium/{winner}
     */
    public function updatePodium(Request $request, Tournament $tournament, TournamentWinner $winner): JsonResponse
    {
        // Verify winner belongs to this tournament
        if ($winner->tournament_id !== $tournament->id) {
            return response()->json([
                'error' => 'Winner not found for this tournament',
            ], 404);
        }

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'username' => 'nullable|string',
            'osu_id' => 'nullable|integer',
        ]);

        $podiumBackfill = app(ParticipationPodiumBackfillService::class);
        $groupId = $podiumBackfill->groupIdForWinner($winner)
            ?? $podiumBackfill->assignWinnersToGroup($tournament, [$winner->id]);
        $oldData = [
            'user_id' => $winner->user_id,
            'username' => $winner->username,
            'osu_id' => $winner->osu_id,
        ];

        $winner->update($validated);
        $winner->refresh();

        if ($oldData['user_id'] && $oldData['user_id'] !== $winner->user_id) {
            $podiumBackfill->deletePodiumParticipationRecord($tournament->id, $oldData['user_id'], $groupId);
        }

        if ($winner->user_id) {
            $podiumUser = User::query()->find($winner->user_id);
            if ($podiumUser) {
                $podiumBackfill->backfillFor($podiumUser);
            }
        }
        $podiumBackfill->backfillTournamentGroup($tournament->id, $groupId);

        AdminAuditLog::log(
            auth()->user(),
            'tournament.podium_winner_updated',
            $tournament,
            [
                'winner_id' => $winner->id,
                'old' => $oldData,
                'new' => $validated,
            ]
        );

        return response()->json([
            'success' => true,
            'winner' => $winner,
        ]);
    }

    /**
     * Destroy a podium winner
     *
     * DELETE /admin/tournaments/{tournament}/podium/{winner}
     */
    public function destroyPodium(Request $request, Tournament $tournament, TournamentWinner $winner): JsonResponse
    {
        // Verify winner belongs to this tournament
        if ($winner->tournament_id !== $tournament->id) {
            return response()->json([
                'error' => 'Winner not found for this tournament',
            ], 404);
        }

        $podiumBackfill = app(ParticipationPodiumBackfillService::class);
        $groupId = $podiumBackfill->groupIdForWinner($winner);
        $userId = $winner->user_id;
        $winner->delete();
        $podiumBackfill->deletePodiumParticipationRecord($tournament->id, $userId, $groupId);
        if ($groupId !== null) {
            $podiumBackfill->backfillTournamentGroup($tournament->id, $groupId);
        }

        AdminAuditLog::log(
            auth()->user(),
            'tournament.podium_winner_removed',
            $tournament,
            [
                'winner_id' => $winner->id,
                'user_id' => $winner->user_id,
                'username' => $winner->username,
                'placement' => $winner->placement,
            ]
        );

        return response()->json([
            'success' => true,
            'fragment' => $this->podiumComponentHtml($tournament),
        ]);
    }

    public function groupPodiumWinners(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'winner_ids' => ['required', 'array', 'min:1'],
            'winner_ids.*' => ['integer', 'distinct', 'exists:tournament_winners,id'],
            'source_winner_id' => ['nullable', 'integer', 'exists:tournament_winners,id'],
            'team_name' => ['nullable', 'string', 'max:150'],
        ]);

        $groupId = app(ParticipationPodiumBackfillService::class)
            ->assignWinnersToGroup(
                $tournament,
                array_map('intval', $validated['winner_ids']),
                isset($validated['source_winner_id']) ? (int) $validated['source_winner_id'] : null,
                array_key_exists('team_name', $validated) ? $validated['team_name'] : null,
            );

        AdminAuditLog::log(
            auth()->user(),
            'tournament.podium_winner_updated',
            $tournament,
            [
                'winner_ids' => array_values($validated['winner_ids']),
                'podium_group_id' => $groupId,
                'source_winner_id' => $validated['source_winner_id'] ?? null,
                'team_name' => $validated['team_name'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'podium_group_id' => $groupId,
            'fragment' => $this->podiumComponentHtml($tournament),
        ]);
    }

    public function splitPodiumWinner(Request $request, Tournament $tournament, TournamentWinner $winner): JsonResponse
    {
        if ($winner->tournament_id !== $tournament->id) {
            return response()->json([
                'error' => 'Winner not found for this tournament',
            ], 404);
        }

        $groupId = app(ParticipationPodiumBackfillService::class)->splitWinnerToNewGroup($winner);

        AdminAuditLog::log(
            auth()->user(),
            'tournament.podium_winner_updated',
            $tournament,
            [
                'winner_id' => $winner->id,
                'podium_group_id' => $groupId,
            ]
        );

        return response()->json([
            'success' => true,
            'podium_group_id' => $groupId,
            'fragment' => $this->podiumComponentHtml($tournament),
        ]);
    }

    public function splitPodiumWinnersToNewGroup(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'winner_ids' => ['required', 'array', 'min:1'],
            'winner_ids.*' => ['integer', 'distinct', 'exists:tournament_winners,id'],
        ]);

        $groupId = app(ParticipationPodiumBackfillService::class)
            ->splitWinnersToNewGroup(
                $tournament,
                array_map('intval', $validated['winner_ids']),
            );

        AdminAuditLog::log(
            auth()->user(),
            'tournament.podium_winner_updated',
            $tournament,
            [
                'winner_ids' => array_values($validated['winner_ids']),
                'podium_group_id' => $groupId,
                'action' => 'split_to_new_group',
            ]
        );

        return response()->json([
            'success' => true,
            'podium_group_id' => $groupId,
            'fragment' => $this->podiumComponentHtml($tournament),
        ]);
    }

    public function getPodiumComponent(Tournament $tournament): Response
    {
        return response($this->podiumComponentHtml($tournament));
    }

    private function podiumComponentHtml(Tournament $tournament): string
    {
        $tournament->load(['winners.user']);

        return view('components.admin.tournaments.tournament-podium-management', [
            'tournament' => $tournament,
        ])->render();
    }

    /**
     * Add badge URL to tournament (placement-based)
     *
     * POST /admin/tournaments/{tournament}/badges
     */
    public function addBadgeUrl(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2048',
            'placement' => 'required|integer|in:1,2,3',
        ]);

        // Get current badge_urls structure (or empty array if null)
        $badgeUrls = $tournament->badge_urls ?? [];

        // Check for duplicates in this placement
        $placementBadges = $badgeUrls[$validated['placement']] ?? [];
        if (in_array($validated['url'], $placementBadges)) {
            return response()->json([
                'error' => 'This badge URL already exists for this placement',
            ], 400);
        }

        // Append new URL to placement array
        $badgeUrls[$validated['placement']][] = $validated['url'];

        // Update tournament
        $tournament->update(['badge_urls' => $badgeUrls]);

        AdminAuditLog::log(
            auth()->user(),
            'tournament.badge_url_added',
            $tournament,
            [
                'badge_url' => $validated['url'],
                'placement' => $validated['placement'],
            ]
        );

        return response()->json([
            'success' => true,
            'badge_url' => $validated['url'],
            'placement' => $validated['placement'],
        ], 201);
    }

    /**
     * Remove badge URL from tournament (placement-based)
     *
     * DELETE /admin/tournaments/{tournament}/badges
     */
    public function removeBadgeUrl(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2048',
            'placement' => 'required|integer|in:1,2,3',
        ]);

        $badgeUrls = $tournament->badge_urls ?? [];

        if (isset($badgeUrls[$validated['placement']])) {
            $badgeUrls[$validated['placement']] = array_values(array_filter(
                $badgeUrls[$validated['placement']],
                fn ($url) => $url !== $validated['url']
            ));

            $tournament->update(['badge_urls' => $badgeUrls]);
        }

        AdminAuditLog::log(
            auth()->user(),
            'tournament.badge_url_removed',
            $tournament,
            [
                'badge_url' => $validated['url'],
                'placement' => $validated['placement'],
            ]
        );

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Designate a staff member as the tournament host
     *
     * POST /admin/tournaments/{tournament}/make-host/{user}
     */
    public function makeHost(Request $request, Tournament $tournament, User $user): JsonResponse
    {
        // Verify user is a staff member with organizer role for this tournament
        $isOrganizer = TournamentStaff::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->where('role', 'organizer')
            ->exists();

        if (! $isOrganizer) {
            return response()->json([
                'success' => false,
                'message' => 'User must be an organizer to be designated as host',
            ], 400);
        }

        $oldHostOsuId = $tournament->host_osu_id;
        $oldHostUsername = $tournament->host_username;

        // Update tournament host
        $tournament->update([
            'host_osu_id' => $user->osu_id,
            'host_username' => $user->username,
        ]);

        AdminAuditLog::log(
            auth()->user(),
            'tournament.host_changed',
            $tournament,
            [
                'old_host_osu_id' => $oldHostOsuId,
                'old_host_username' => $oldHostUsername,
                'new_host_osu_id' => $user->osu_id,
                'new_host_username' => $user->username,
                'user_id' => $user->id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "{$user->username} is now the tournament host",
        ]);
    }

    /**
     * Get staff list component HTML (for AJAX refresh)
     *
     * GET /admin/tournaments/{tournament}/staff-component
     */
    public function getStaffComponent(Tournament $tournament): Response
    {
        // Reload tournament with fresh staff data
        /** @phpstan-ignore-next-line Dynamic Eloquent scope. */
        $tournament = Tournament::withStaffSortedByRole()->findOrFail($tournament->id);

        // Render just the staff management component
        $html = view('components.admin.tournaments.tournament-staff-management', [
            'tournament' => $tournament,
        ])->render();

        return response($html);
    }

    /**
     * Restore rejected tournament to pending review
     *
     * POST /admin/tournaments/{tournament}/restore
     */
    public function restore(Request $request, Tournament $tournament): JsonResponse
    {
        try {
            // Validate tournament is rejected or approved
            if ($tournament->status !== 'rejected' && $tournament->status !== 'approved') {
                return response()->json([
                    'message' => 'Tournament cannot be restored',
                    'error' => 'Only rejected or approved tournaments can be restored to pending',
                ], 400);
            }

            /** @var User $user */
            $user = $request->user();

            $tournament->restoreToPending($user);

            AdminAuditLog::log(
                $user,
                'tournament.restored',
                $tournament,
                ['restored_at' => now()]
            );

            return response()->json([
                'message' => 'Tournament restored to pending review',
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Error during restore', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error restoring tournament',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview conflicts before re-parse
     * PHASE 3: Conflict Resolution
     *
     * GET /admin/tournaments/{tournament}/conflicts?preview_data={...}
     */
    public function previewConflicts(Request $request, Tournament $tournament): JsonResponse
    {
        // Support both query parameter and JSON body
        $previewData = $request->input('preview_data');

        if (is_string($previewData)) {
            $previewData = json_decode($previewData, true);
        }

        // Validate decoded data manually
        if (! is_array($previewData)) {
            return response()->json([
                'message' => 'The preview data field is required.',
                'errors' => [
                    'preview_data' => [
                        'The preview data field is required.',
                    ],
                ],
            ], 422);
        }

        // Detect conflicts using Tournament model method
        $conflicts = $tournament->detectReparseConflicts($previewData);

        return response()->json([
            'conflicts' => $conflicts,
            'count' => count($conflicts),
        ], 200);
    }

    /**
     * Re-parse with conflict resolution
     * PHASE 3: Conflict Resolution
     * Admin chooses which fields to keep manual vs use parsed
     *
     * POST /admin/tournaments/{tournament}/conflicts/reparse
     */
    public function reparseWithResolution(Request $request, Tournament $tournament): JsonResponse
    {
        // Support both query parameter and JSON body
        $parsedData = $request->input('parsed_data');

        if (is_string($parsedData)) {
            $parsedData = json_decode($parsedData, true);
        }

        $resolution = $request->input('resolution');

        if (is_string($resolution)) {
            $resolution = json_decode($resolution, true);
        }

        // Validate decoded data manually
        if (! is_array($parsedData)) {
            return response()->json([
                'message' => 'The parsed data field is required.',
                'errors' => [
                    'parsed_data' => [
                        'The parsed data field is required.',
                    ],
                ],
            ], 422);
        }

        if (! is_array($resolution)) {
            return response()->json([
                'message' => 'The resolution field is required.',
                'errors' => [
                    'resolution' => [
                        'The resolution field is required.',
                    ],
                ],
            ], 422);
        }

        // Validate resolution values
        foreach ($resolution as $field => $choice) {
            if (! in_array($choice, ['manual', 'parsed'])) {
                return response()->json([
                    'message' => 'Invalid resolution choice.',
                    'errors' => [
                        "resolution.{$field}" => [
                            'The resolution must be either manual or parsed.',
                        ],
                    ],
                ], 422);
            }
        }

        $keptManual = [];
        $usedParsed = [];

        // Process resolution for each field
        foreach ($resolution as $field => $choice) {
            if ($choice === 'manual') {
                // Keep manual - skip updating this field
                $keptManual[] = $field;
            } elseif ($choice === 'parsed') {
                // Use parsed - mark field_sources as parsed
                $currentFieldSources = $tournament->field_sources ?? [];
                $currentFieldSources[$field] = 'parsed';
                $tournament->setAttribute('field_sources', $currentFieldSources);
                $usedParsed[] = $field;
            }
        }

        // Update tournament with only non-manual fields
        $tournament->updateFromParsedData($parsedData, auth()->id());

        // Apply manual field preservation after update
        if (! empty($keptManual)) {
            $currentFieldSources = $tournament->field_sources ?? [];
            foreach ($keptManual as $field) {
                $currentFieldSources[$field] = 'manual';
            }
            $tournament->setAttribute('field_sources', $currentFieldSources);
            $tournament->save();
        }

        // Log action with details
        AdminAuditLog::log(
            auth()->user(), // Pass User object instead of ID
            'tournament.reparse_resolved',
            $tournament,
            [
                'kept_manual' => $keptManual,
                'used_parsed' => $usedParsed,
            ]
        );

        return response()->json([
            'message' => 'Tournament re-parsed with conflict resolution',
            'kept_manual' => $keptManual,
            'used_parsed' => $usedParsed,
        ], 200);
    }

    /**
     * Delete tournament (soft delete)
     *
     * DELETE /admin/tournaments/{tournament}
     */
    public function destroy(Request $request, Tournament $tournament): JsonResponse
    {
        $tournament->delete();

        AdminAuditLog::log(
            $request->user(),
            'tournament.deleted',
            $tournament,
            ['deleted_at' => now()]
        );

        return response()->json([
            'message' => 'Tournament deleted successfully',
        ], 200);
    }

    /**
     * Show tournament parse history
     *
     * GET /admin/tournaments/{tournament}/parse-history
     */
    public function parseHistory(Tournament $tournament): View
    {
        $histories = $tournament->parseHistories()->paginate(20);

        return view('admin.tournaments.parse-history', compact('tournament', 'histories'));
    }

    /**
     * Re-parse tournament from forum topic
     *
     * POST /admin/tournaments/{tournament}/reparse
     */
    public function reparse(
        Request $request,
        Tournament $tournament,
        AdminTournamentParseService $parseService
    ): RedirectResponse|JsonResponse {
        // Verify tournament has a forum topic
        if (! $tournament->forum_topic_id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot re-parse tournament - no forum topic associated',
                ], 400);
            }

            $referer = $request->header('referer');
            $fallbackUrl = route('admin.tournaments.show', $tournament);

            return redirect($referer ?: $fallbackUrl)
                ->with('error', 'Cannot re-parse tournament - no forum topic associated');
        }

        $operation = AdminAsyncOperation::query()->create([
            'type' => AdminAsyncOperation::TYPE_TOURNAMENT_REPARSE,
            'tournament_id' => $tournament->id,
            'status' => AdminAsyncOperation::STATUS_PENDING,
            'total' => 4,
            'message' => 'Queued tournament re-parse',
            'errors' => [],
            'result' => [],
        ]);

        $transactionId = $parseService->queueFullParse($tournament, 'reparse', $operation->id);

        // Log audit trail
        AdminAuditLog::log(
            $request->user(),
            'tournament.reparsed',
            $tournament,
            [
                'forum_topic_id' => $tournament->forum_topic_id,
                'previous_parse_count' => $tournament->parse_count,
                'transaction_id' => $transactionId,
                'operation_id' => $operation->id,
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'operation_id' => $operation->id,
                'transaction_id' => $transactionId,
                'message' => 'Tournament queued for full re-parsing',
            ], 202);
        }

        $referer = $request->header('referer');
        $fallbackUrl = route('admin.tournaments.show', $tournament);

        return redirect($referer ?: $fallbackUrl)
            ->with('success', "Tournament queued for full re-parsing (fields + staff). Transaction ID: {$transactionId}");
    }

    /**
     * Show parse history changes
     *
     * GET /admin/tournaments/{tournament}/parse-history/{history}
     */
    public function showParseHistory(Tournament $tournament, TournamentParseHistory $history): View
    {
        // Verify history belongs to tournament
        if ($history->tournament_id !== $tournament->id) {
            abort(404);
        }

        return view('admin.tournaments.parse-history-show', compact('tournament', 'history'));
    }

    /**
     * Delete parse history entry
     *
     * DELETE /admin/tournaments/{tournament}/parse-history/{history}
     */
    public function deleteParseHistory(Request $request, Tournament $tournament, TournamentParseHistory $history): JsonResponse
    {
        // Verify history belongs to tournament
        if ($history->tournament_id !== $tournament->id) {
            return response()->json([
                'error' => 'Parse history not found for this tournament',
            ], 404);
        }

        $history->delete();

        AdminAuditLog::log(
            $request->user(),
            'tournament.parse_history_deleted',
            $tournament,
            [
                'history_id' => $history->id,
                'parsed_at' => $history->created_at,
            ]
        );

        return response()->json([
            'message' => 'Parse history deleted successfully',
        ], 200);
    }
}
