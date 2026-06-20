<?php

namespace App\Http\Controllers;

use App\Helpers\StaffRoleHelper;
use App\Http\Requests\Admin\TournamentStoreRequest;
use App\Models\Tournament;
use App\Models\TournamentCorrection;
use App\Models\TournamentCorrectionComment;
use App\Models\User;
use App\Services\CldrService;
use App\Services\ForumParser;
use App\Services\OsuApiService;
use App\Services\TournamentCorrectionService;
use App\Services\TournamentMetadataNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TournamentCorrectionController extends Controller
{
    public function createTournament(): View
    {
        $tournament = new Tournament([
            'modes' => [],
            'format_tags' => [],
            'format_structure' => ['stages' => []],
            'restricted_countries' => [],
            'badge_urls' => [],
        ]);
        $tournament->setRelation('staff', collect());
        $tournament->setRelation('winners', collect());

        return view('tournaments.corrections.create', [
            'tournament' => $tournament,
            'staffRoles' => array_keys(StaffRoleHelper::getAvailableRoles()),
            'isCreation' => true,
        ]);
    }

    public function checkDuplicates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'forum_post_url' => ['nullable', 'url', 'max:500'],
        ]);
        $topicId = $this->topicIdFromUrl($validated['forum_post_url'] ?? null);

        return response()->json($this->confirmationOptions($topicId));
    }

    public function storeTournament(
        Request $request,
        TournamentCorrectionService $service,
        TournamentMetadataNormalizer $normalizer,
        OsuApiService $osuApi,
        ForumParser $parser
    ): RedirectResponse {
        $input = $request->all();
        if ($request->has('format_structure_present')) {
            $input['format_structure'] = is_array($input['format_structure'] ?? null)
                ? $input['format_structure']
                : ['stages' => []];
            $input['format_structure']['stages'] ??= [];
        }

        $input = $normalizer->normalize($input, inferLegacyFormat: false);
        $request->replace($input);
        $validated = $request->validate($this->creationRules());

        if (! $request->boolean('creation_confirmed')) {
            throw ValidationException::withMessages([
                'creation_confirmed' => ['Confirm this tournament request before submitting.'],
            ]);
        }

        foreach (array_keys(StaffRoleHelper::getAvailableRoles()) as $role) {
            $validated["staff_{$role}"] = $request->input("staff_{$role}");
        }
        $validated['podium_groups'] = $request->input('podium_groups', []);

        foreach (TournamentCorrectionService::UTC_DATETIME_FIELDS as $field) {
            $value = $validated[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $validated[$field] = CarbonImmutable::parse($value, 'UTC');
            }
        }

        $topicId = $this->topicIdFromUrl($validated['forum_post_url'] ?? null);
        $options = $this->confirmationOptions($topicId);
        $pendingId = data_get($options, 'pending.id');
        $selectedId = $request->integer('selected_tournament_id') ?: null;
        $approvedIds = collect($options['approved'])->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($pendingId) {
            $selectedId = null;
        } elseif ($selectedId !== null && ! in_array($selectedId, $approvedIds, true)) {
            throw ValidationException::withMessages([
                'selected_tournament_id' => ['The selected duplicate is no longer an available approved tournament. Please confirm again.'],
            ]);
        }

        $targetId = $pendingId ?: $selectedId;
        if ($targetId) {
            $this->mergeExistingStaff($validated, Tournament::query()->with('staff')->findOrFail($targetId));
        }

        if ($topicId !== null) {
            $topicData = $osuApi->getForumTopic($topicId);
            $postContent = is_array($topicData) ? ($topicData['post'] ?? data_get($topicData, 'posts.0.body.raw')) : null;
            if (is_string($postContent)) {
                $this->mergeParsedStaff($validated, $parser->parseStaffFromBBcode($postContent));
            }
        }

        if ($targetId) {
            $target = Tournament::query()->findOrFail($targetId);
            $correction = $service->createCorrection($target, $request->user(), $validated);

            return redirect()
                ->route('tournaments.corrections.history', $target)
                ->with('success', "Correction #{$correction->id} submitted for admin review.");
        }

        $correction = $service->createTournamentRequest($request->user(), $validated, $topicId);

        return redirect()
            ->route('tournament-corrections.show', $correction)
            ->with('success', __('tournaments.add.success', ['id' => $correction->id]))
            ->with('tournament_request_success', __('tournaments.add.success', ['id' => $correction->id]));
    }

    public function create(Tournament $tournament): View|RedirectResponse
    {
        if ($tournament->status !== Tournament::STATUS_APPROVED) {
            abort(404);
        }

        $pendingCorrection = $tournament->corrections()->active()->with('submitter')->first();
        if ($pendingCorrection) {
            return redirect()
                ->route('tournaments.corrections.history', $tournament)
                ->with('error', 'This tournament already has a pending correction.');
        }

        return view('tournaments.corrections.create', [
            'tournament' => $tournament->load(['staff', 'winners.user']),
            'staffRoles' => array_keys(StaffRoleHelper::getAvailableRoles()),
            'isCreation' => false,
        ]);
    }

    public function store(
        Request $request,
        Tournament $tournament,
        TournamentCorrectionService $service,
        TournamentMetadataNormalizer $normalizer
    ): RedirectResponse {
        if ($tournament->status !== Tournament::STATUS_APPROVED) {
            abort(404);
        }

        $input = $request->all();
        if ($request->has('format_structure_present')) {
            $formatStructure = is_array($input['format_structure'] ?? null)
                ? $input['format_structure']
                : [];
            if (! array_key_exists('stages', $formatStructure)) {
                $formatStructure['stages'] = [];
            }
            $input['format_structure'] = $formatStructure;
        }

        $utcDateInput = collect(TournamentCorrectionService::UTC_DATETIME_FIELDS)
            ->filter(fn (string $field): bool => array_key_exists($field, $input))
            ->mapWithKeys(fn (string $field): array => [$field => $input[$field]])
            ->all();
        $request->replace([
            ...$normalizer->normalize($input, inferLegacyFormat: false),
            ...$utcDateInput,
        ]);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:256'],
            'submitter_note' => ['nullable', 'string', 'max:1000'],
            'modes' => ['nullable', 'array'],
            'modes.*' => [
                'nullable',
                function ($attribute, $value, $fail): void {
                    if (is_string($value)) {
                        if (! in_array($value, ['osu', 'taiko', 'catch', 'fruits', 'mania'], true)) {
                            $fail("The selected {$attribute} is invalid.");
                        }

                        return;
                    }

                    if (! is_array($value) || ! in_array($value['mode'] ?? null, ['osu', 'taiko', 'catch', 'fruits', 'mania'], true)) {
                        $fail("The selected {$attribute} is invalid.");
                    }
                },
            ],
            'mania_variants' => ['nullable', 'array'],
            'mania_variants.*' => ['required', 'string', 'in:mania_4k,mania_7k,mania_other'],
            'vs_size' => ['nullable', 'integer', 'min:1', 'max:16'],
            'team_size_min' => ['nullable', 'integer', 'min:1', 'max:16'],
            'team_size_max' => ['nullable', 'integer', 'min:1', 'max:16', 'gte:team_size_min'],
            'rank_range_min' => ['nullable', 'integer', 'min:1'],
            'rank_range_max' => ['nullable', 'integer', 'min:1'],
            'registration_start' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'registration_end' => ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:registration_start'],
            'tournament_start' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'tournament_end' => ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:tournament_start'],
            'is_badge' => ['nullable', 'boolean'],
            'is_bws' => ['nullable', 'boolean'],
            'badge_urls' => ['nullable', 'array'],
            'badge_urls.1' => ['nullable', 'array'],
            'badge_urls.2' => ['nullable', 'array'],
            'badge_urls.3' => ['nullable', 'array'],
            'badge_urls.*.*' => ['nullable', 'url', 'max:2048'],
            'badge_status' => ['nullable', 'in:approved,pending,rejected'],
            'banner_url' => ['nullable', 'url', 'max:2048'],
            'forum_post_url' => ['nullable', 'url', 'max:500'],
            'spreadsheet_url' => ['nullable', 'url', 'max:500'],
            'discord_url' => ['nullable', 'url', 'max:500'],
            'twitch_url' => ['nullable', 'url', 'max:500'],
            'registration_url' => ['nullable', 'url', 'max:2048'],
            'bracket_url' => ['nullable', 'url', 'max:2048'],
            'format' => ['nullable', 'string', 'max:100'],
            'team_formation_style' => ['nullable', 'string', 'in:standard,draft,auction,world_cup,suiji'],
            'format_tags' => ['nullable', 'array'],
            'format_tags.*' => ['required', 'string', 'in:draft,auction,world_cup,suiji,battle_royale'],
            'format_structure' => ['nullable', 'array'],
            'format_structure.stages' => ['nullable', 'array', 'max:8'],
            'format_structure.stages.*.type' => ['nullable', 'string', 'in:qualifier,group_stage,swiss_round,bracket,battle_royale'],
            'format_structure.stages.*.name' => ['nullable', 'string', 'max:100'],
            'format_structure.stages.*.notes' => ['nullable', 'string', 'max:500'],
            'format_structure.stages.*.entry_type' => ['nullable', 'string', 'in:winner_only,winner_loser_hybrid'],
            'format_structure.stages.*.elimination_type' => ['nullable', 'string', 'in:single_elimination,double_elimination'],
            'format_structure.stages.*.advance_count' => ['nullable', 'integer', 'min:1', 'max:4096'],
            'format_structure.stages.*.round_count' => ['nullable', 'integer', 'min:1', 'max:64'],
            'format_structure.stages.*.group_count' => ['nullable', 'integer', 'min:1', 'max:512'],
            'format_structure.stages.*.teams_per_group' => ['nullable', 'integer', 'min:1', 'max:512'],
            'format_structure.stages.*.start_round_size' => ['nullable', 'integer', 'min:2', 'max:1024', 'regex:/^(2|4|8|16|32|64|128|256|512|1024)$/'],
            'format_structure.stages.*.lobby_count' => ['nullable', 'integer', 'min:1', 'max:512'],
            'format_structure.stages.*.players_per_lobby' => ['nullable', 'integer', 'min:1', 'max:512'],
            'format_structure.stages.*.advance_per_lobby' => ['nullable', 'integer', 'min:1', 'max:512'],
            'format_structure.stages.*.eliminated_per_map' => ['nullable', 'integer', 'min:1', 'max:512'],
            'format_structure.stages.*.elimination_rule' => ['nullable', 'string', 'max:100'],
            'format_structure.stages.*.win_condition' => ['nullable', 'string', 'max:100'],
            'star_rating_first' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'star_rating_last' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'star_rating_qualifier' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'start_round_size' => ['nullable', 'integer', 'min:2', 'max:1024', 'regex:/^(2|4|8|16|32|64|128|256|512|1024)$/'],
            'restricted_countries' => ['nullable', 'array'],
            'restricted_countries.*' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Z]{2}$/',
                function ($attribute, $value, $fail): void {
                    if (! app(CldrService::class)->isValidTerritoryCode((string) $value)) {
                        $fail("The {$attribute} must be a valid CLDR territory code.");
                    }
                },
            ],
            'bws_base_exponent' => ['nullable', 'numeric', 'between:0.9,1.0'],
            'bws_badge_power' => ['nullable', 'numeric', 'between:1,5'],
            'bws_divisor' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'bws_badge_age_cutoff' => ['nullable', 'date'],
            'staff_*' => ['nullable', 'string', 'max:5000'],
            'podium_groups' => ['nullable', 'array'],
            'podium_groups.*' => ['nullable', 'array'],
            'podium_groups.*.*.group_key' => ['nullable', 'string', 'max:100'],
            'podium_groups.*.*.group_id' => ['nullable', 'string', 'max:100'],
            'podium_groups.*.*.team_name' => ['nullable', 'string', 'max:255'],
            'podium_groups.*.*.usernames' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach (array_keys(StaffRoleHelper::getAvailableRoles()) as $role) {
            $validated["staff_{$role}"] = $request->input("staff_{$role}");
        }
        if ($request->has('podium_groups')) {
            $validated['podium_groups'] = $request->input('podium_groups', []);
        }
        foreach (TournamentCorrectionService::UTC_DATETIME_FIELDS as $field) {
            $value = $validated[$field] ?? null;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $validated[$field] = CarbonImmutable::createFromFormat('Y-m-d\TH:i', trim($value), 'UTC');
        }

        try {
            $correction = $service->createCorrection($tournament, $request->user(), $validated);
        } catch (\InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('tournaments.corrections.history', $tournament)
            ->with('success', "Correction #{$correction->id} submitted for admin review.");
    }

    public function history(Tournament $tournament, TournamentCorrectionService $service): View
    {
        $corrections = $tournament->corrections()
            ->with(['submitter', 'reviewer'])
            ->latest()
            ->paginate(5);

        $orderedCorrectionChanges = $corrections->getCollection()
            ->mapWithKeys(fn ($correction): array => [$correction->id => $service->orderedChanges($correction)])
            ->all();
        $groupedStaffHistoryChanges = $corrections->getCollection()
            ->mapWithKeys(fn ($correction): array => [$correction->id => $service->groupedStaffHistoryChanges($correction)])
            ->all();
        $groupedPodiumHistoryChanges = $corrections->getCollection()
            ->mapWithKeys(fn ($correction): array => [$correction->id => $service->groupedPodiumHistoryChanges($correction)])
            ->all();

        return view('tournaments.corrections.history', compact(
            'tournament',
            'corrections',
            'orderedCorrectionChanges',
            'groupedStaffHistoryChanges',
            'groupedPodiumHistoryChanges'
        ));
    }

    public function show(TournamentCorrection $correction, TournamentCorrectionService $service): View
    {
        $correction->load([
            'tournament',
            'submitter',
            'reviewer',
            'comments.author',
        ]);

        return view('tournaments.corrections.show', [
            'correction' => $correction,
            'groupedChanges' => $service->groupedOrderedChanges($correction),
            'staffGroups' => $service->groupedStaffHistoryChanges($correction),
            'podiumGroups' => $service->groupedPodiumHistoryChanges($correction),
            'groupedFailures' => $service->groupedApplyFailures($correction),
            'canComment' => $this->canComment($correction, request()->user()),
        ]);
    }

    public function status(TournamentCorrection $correction): JsonResponse
    {
        return response()->json([
            'status' => $correction->status,
            'processing_total' => $correction->processing_total,
            'processing_completed' => $correction->processing_completed,
            'processing_percent' => $correction->processingPercent(),
            'failures' => data_get($correction->admin_decisions, 'apply_failures', []),
            'finished' => $correction->status !== TournamentCorrection::STATUS_PROCESSING,
        ]);
    }

    public function storeComment(Request $request, TournamentCorrection $correction): RedirectResponse
    {
        abort_unless($this->canComment($correction, $request->user()), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000', 'regex:/\S/'],
        ]);

        TournamentCorrectionComment::query()->create([
            'tournament_correction_id' => $correction->id,
            'user_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        return redirect()
            ->route('tournament-corrections.show', $correction)
            ->with('success', 'Reply posted.');
    }

    private function canComment(TournamentCorrection $correction, User $user): bool
    {
        return $user->id === $correction->submitted_by || $user->isAdminOrMaster();
    }

    /**
     * @return array<string, mixed>
     */
    private function creationRules(): array
    {
        $rules = (new TournamentStoreRequest)->rules();
        $rules['submitter_note'] = ['nullable', 'string', 'max:1000'];
        $rules['creation_confirmed'] = ['nullable', 'boolean'];
        $rules['selected_tournament_id'] = ['nullable', 'integer'];
        $rules['staff_*'] = ['nullable', 'string', 'max:5000'];
        $rules['podium_groups'] = ['nullable', 'array'];
        $rules['podium_groups.*'] = ['nullable', 'array'];
        $rules['podium_groups.*.*.group_key'] = ['nullable', 'string', 'max:100'];
        $rules['podium_groups.*.*.group_id'] = ['nullable', 'string', 'max:100'];
        $rules['podium_groups.*.*.team_name'] = ['nullable', 'string', 'max:255'];
        $rules['podium_groups.*.*.usernames'] = ['nullable', 'string', 'max:5000'];
        $rules['badge_urls.*'] = ['nullable', 'array'];
        $rules['badge_urls.*.*'] = ['nullable', 'url', 'max:2048'];

        return $rules;
    }

    private function topicIdFromUrl(?string $url): ?int
    {
        if (! filled($url)) {
            return null;
        }

        if (! preg_match('~^https?://(?:www\.)?osu\.ppy\.sh/community/forums/topics/(\d+)(?:[/?#].*)?$~i', trim($url), $matches)) {
            throw ValidationException::withMessages([
                'forum_post_url' => ['Enter an osu! forum topic URL.'],
            ]);
        }

        return (int) $matches[1];
    }

    /**
     * @return array{topic_id: int|null, pending: array{id: int, title: string}|null, approved: list<array{id: int, title: string, url: string}>}
     */
    private function confirmationOptions(?int $topicId): array
    {
        if ($topicId === null) {
            return ['topic_id' => null, 'pending' => null, 'approved' => []];
        }

        $matches = Tournament::query()
            ->where('forum_topic_id', $topicId)
            ->whereIn('status', [Tournament::STATUS_PENDING, Tournament::STATUS_APPROVED])
            ->orderBy('id')
            ->get(['id', 'title', 'status']);
        $pending = $matches->firstWhere('status', Tournament::STATUS_PENDING);

        return [
            'topic_id' => $topicId,
            'pending' => $pending ? ['id' => $pending->id, 'title' => $pending->title] : null,
            'approved' => $matches
                ->where('status', Tournament::STATUS_APPROVED)
                ->map(fn ($tournament): array => [
                    'id' => $tournament->id,
                    'title' => $tournament->title,
                    'url' => route('tournaments.show', $tournament),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, array{osu_id: int|null, username: string, role: string}>  $staff
     */
    private function mergeParsedStaff(array &$validated, array $staff): void
    {
        $availableRoles = array_keys(StaffRoleHelper::getAvailableRoles());

        foreach ($staff as $member) {
            $username = trim((string) ($member['username'] ?? ''));
            if ($username === '') {
                continue;
            }

            $role = strtolower((string) ($member['role'] ?? 'other'));
            $role = in_array($role, $availableRoles, true) ? $role : 'other';
            $field = "staff_{$role}";
            $names = collect(preg_split('/[\r\n,]+/', (string) ($validated[$field] ?? '')) ?: [])
                ->map(fn (string $name): string => trim($name))
                ->filter()
                ->push($username)
                ->unique(fn (string $name): string => strtolower($name))
                ->values();
            $validated[$field] = $names->implode("\n");
        }
    }

    /**
     * Keep topic-derived staff proposals additive when an add request resolves
     * to an existing pending or approved tournament.
     *
     * @param  array<string, mixed>  $validated
     */
    private function mergeExistingStaff(array &$validated, Tournament $tournament): void
    {
        foreach (array_keys(StaffRoleHelper::getAvailableRoles()) as $role) {
            $field = "staff_{$role}";
            $existing = $tournament->staff
                ->filter(fn ($user): bool => ($user->pivot->role ?? null) === $role)
                ->pluck('username');
            $submitted = collect(preg_split('/[\r\n,]+/', (string) ($validated[$field] ?? '')) ?: []);
            $validated[$field] = $existing
                ->merge($submitted)
                ->map(fn (string $name): string => trim($name))
                ->filter()
                ->unique(fn (string $name): string => strtolower($name))
                ->values()
                ->implode("\n");
        }
    }
}
