@php
    $canManageParticipation = $canManageParticipation ?? $isOwnProfile;
    $availableYears = collect($availableYears ?? []);
    $modeOrder = ['osu' => 0, 'taiko' => 1, 'fruits' => 2, 'catch' => 2, 'mania' => 3];
    $availableModes = collect($availableModes ?? [])
        ->sortBy(fn ($mode) => $modeOrder[$mode] ?? 99)
        ->values();
    $participationIndices = collect($participationIndices ?? []);
    $displayTeammatesByRecord = collect($displayTeammatesByRecord ?? []);
    $displayTeammateBwsRanksByRecord = collect($displayTeammateBwsRanksByRecord ?? []);
    $statGroups = [
        'total' => ['total_tournaments', 'total_unique_teammates', 'total_unique_countries'],
        'country' => ['most_common_country', 'common_country_teammates', 'most_teamed_user'],
        'rate' => ['win_rate', 'top_3_rate'],
        'podium' => ['first_placements', 'second_placements', 'third_placements'],
    ];
    $podiumStatClasses = [
        'first_placements' => 'text-yellow-400',
        'second_placements' => 'text-slate-300',
        'third_placements' => 'text-orange-400',
    ];
@endphp

@if($includeParticipationShell ?? true)
<script>
window.participationDialog = (config) => ({
    ...config,
    open: false,
    mode: 'create',
    formAction: config.storeUrl,
    selectedTournament: null,
    tournamentQuery: '',
    tournamentResults: [],
    tournamentLoading: false,
    stageValue: '',
    placement: '',
    placementPlaceholder: '',
    placementEditable: false,
    placementRangeEditable: false,
    structuredLocked: false,
    memberLocked: false,
    seed: '',
    teamName: '',
    memo: '',
    teammateQuery: '',
    teammateResults: [],
    teammateLoading: false,
    selectedTeammates: [],
    pendingTeammateOsuIds: [],
    pendingOsuId: '',
    manualTeammateInputOpen: false,
    matches: [],
    historySearch: '',
    filterBadged: false,
    filterYear: '',
    filterMode: '',
    focusedTournamentId: config.focusedTournamentId || null,
    recordOffset: config.recordsNextOffset,
    hasMoreRecords: !!config.recordsHasMore,
    recordsLoading: false,
    submitting: false,
    tutorialOpen: false,
    deleteRequestOpen: false,
    deleteRequestAction: '',
    deleteRequestReason: '',
    deleteConfirmOpen: false,
    deleteConfirmAction: '',
    reportOpen: false,
    reportAction: '',
    reportCategory: 'record_correction',
    reportExplanation: '',
    dialogError: '',
    draggingMatchIndex: null,
    lobbySearchOpenKey: null,
    lobbySearchQuery: '',
    lobbySearchResults: [],
    lobbySearchAllResults: [],
    lobbySearchDatasetKey: '',
    lobbySearchLoadedComplete: false,
    lobbySearchTotal: 0,
    lobbySearchOffset: 0,
    lobbySearchLimit: 10,
    lobbySearchLoading: false,
    lobbySearchError: '',
    lobbySearchTimer: null,
    lobbySearchRequestId: 0,
    modeLabels: { osu: 'osu!', taiko: 'osu!taiko', catch: 'osu!catch', fruits: 'osu!catch', mania: 'osu!mania' },

    init() {
        const key = this.participationTutorialKey || 'tourney-method-participation-add-tutorial:v1';
        this.tutorialOpen = this.isOwnProfile && localStorage.getItem(key) !== 'dismissed';
    },

    dismissTutorial() {
        const key = this.participationTutorialKey || 'tourney-method-participation-add-tutorial:v1';
        localStorage.setItem(key, 'dismissed');
        this.tutorialOpen = false;
    },

    recordVisible(element) {
        return true;
    },

    openCreate() {
        this.reset();
        this.open = true;
    },

    openEdit(record, tournament) {
        this.reset();
        this.mode = 'edit';
        this.formAction = `/users/${this.userId}/participation/${record.id}`;
        this.matches = (record.matches || []).map((match) => this.matchFromExisting(match));
        this.selectedTournament = this.tournamentWithSavedMatchStages(tournament, this.matches);
        this.tournamentQuery = tournament.title;
        this.placementRangeEditable = !!record.placement_range_editable;
        this.placementEditable = !this.placementRangeEditable && this.recordHasEditablePlacement(record, tournament);
        this.placement = this.placementFromRecord(record);
        this.structuredLocked = !!record.podium_locked;
        this.memberLocked = !!record.podium_locked && !record.podium_member_editable;
        this.seed = record.seed || '';
        this.teamName = record.team_name || '';
        this.memo = record.memo || '';
        this.selectedTeammates = record.teammates || [];
        this.open = true;
        this.$nextTick(() => {
            this.stageValue = record.stage_value || '';
            this.matches = this.matches.map((match) => ({
                ...match,
                stage: this.canonicalMatchStage(match.stage || ''),
            }));
        });
    },

    recordHasEditablePlacement(record, tournament) {
        const option = (tournament.stage_options || []).find((stage) => stage.value === record.stage_value);

        return !!record.placement_override || !!option?.editable_placement || (option?.allowed_placements || []).length > 0;
    },

    placementFromRecord(record) {
        if (record.placement_min && record.placement_max && record.placement_min !== record.placement_max) {
            return `${record.placement_min}-${record.placement_max}`;
        }

        if (record.placement_override) {
            return record.placement_override;
        }

        if (record.placement_min && record.placement_max && record.placement_min === record.placement_max) {
            return record.placement_min;
        }

        if (record.placement) {
            return record.placement;
        }

        return '';
    },

    tournamentWithSavedMatchStages(tournament, matches) {
        const matchStageOptions = [...(tournament.match_stage_options || [])];

        matches.forEach((match) => {
            const stage = this.canonicalMatchStage(match.stage || '');
            if (stage && !matchStageOptions.includes(stage)) {
                matchStageOptions.push(stage);
            }
        });

        return { ...tournament, match_stage_options: matchStageOptions };
    },

    reset() {
        this.mode = 'create';
        this.formAction = this.storeUrl;
        this.selectedTournament = null;
        this.tournamentQuery = '';
        this.tournamentResults = [];
        this.tournamentLoading = false;
        this.stageValue = '';
        this.placement = '';
        this.placementPlaceholder = '';
        this.placementEditable = false;
        this.placementRangeEditable = false;
        this.structuredLocked = false;
        this.memberLocked = false;
        this.seed = '';
        this.teamName = '';
        this.memo = '';
        this.teammateQuery = '';
        this.teammateResults = [];
        this.teammateLoading = false;
        this.selectedTeammates = [];
        this.pendingTeammateOsuIds = [];
        this.pendingOsuId = '';
        this.manualTeammateInputOpen = false;
        this.matches = [];
        this.draggingMatchIndex = null;
        this.resetLobbySearchSession();
    },

    close() {
        this.open = false;
    },

    async submitParticipationForm(event) {
        if (!this.selectedTournament || this.submitting) return;
        if (this.needsPodiumApprovalConfirmation() && !window.confirm(@js(__('users.participation.confirm_podium_approval')))) return;

        await this.submitParticipationAction(event, () => {
            this.close();
            this.reset();
        });
    },

    async submitParticipationAction(event, onSuccess = null) {
        if (this.submitting) return;

        this.submitting = true;
        this.dialogError = '';
        const form = event.target;
        const action = new URL(form.action, window.location.origin);
        const loadedCount = this.loadedRecordCount();
        this.recordsQueryParams(0).forEach((value, key) => {
            if (key !== 'offset') action.searchParams.set(key, value);
        });
        action.searchParams.set('refresh_limit', String(Math.max(loadedCount, this.recordOffset || 0, 10)));
        const scrollY = window.scrollY;

        try {
            const response = await fetch(action.toString(), {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrf,
                },
            });
            const data = await response.json();

            if (!response.ok) {
                const message = this.errorMessage(data);
                this.dialogError = message;
                this.notify(data.type || 'error', message);
                return;
            }

            this.refreshParticipation(data.html || '');
            if ('next_offset' in data) this.recordOffset = data.next_offset;
            if ('has_more' in data) this.hasMoreRecords = !!data.has_more;
            this.notify(data.type || 'success', data.message || '');
            this.$nextTick(() => window.scrollTo({ top: scrollY }));
            if (onSuccess) onSuccess();
        } finally {
            this.submitting = false;
        }
    },

    errorMessage(data) {
        const errors = data?.errors || {};
        const firstKey = Object.keys(errors)[0];
        if (firstKey && Array.isArray(errors[firstKey]) && errors[firstKey][0]) {
            return errors[firstKey][0];
        }

        return data?.message || 'Unable to update participation.';
    },

    loadedRecordCount() {
        return this.$root.querySelectorAll('[data-participation-record]').length;
    },

    needsPodiumApprovalConfirmation() {
        if (this.mode !== 'create' || !this.selectedTournament) return false;

        const value = String(this.placement || '').trim();
        const option = (this.selectedTournament.stage_options || []).find((stage) => stage.value === this.stageValue);
        const placement = value
            ? (value.includes('-') ? Number(value.split('-')[0]) : Number(value))
            : Number(option?.placement_min || option?.placement || 0);
        if (!placement || placement > 3) return false;

        return !(this.selectedTournament.current_user_podium_placements || []).includes(placement);
    },

    notify(type, message) {
        if (!message) return;

        window.dispatchEvent(new CustomEvent('app-toast', { detail: { type, message } }));
    },

    refreshParticipation(html) {
        if (!html) return;

        const parser = new DOMParser();
        const next = parser.parseFromString(html, 'text/html').querySelector('[data-participation-refresh]');
        const current = this.$root.querySelector('[data-participation-refresh]');
        if (!next || !current) return;

        current.innerHTML = next.innerHTML;
        this.$nextTick(() => {
            if (window.Alpine?.initTree) {
                window.Alpine.initTree(current);
            }
        });
    },

    openDeletionRequestDialog(action) {
        this.deleteRequestAction = action;
        this.deleteRequestReason = '';
        this.deleteRequestOpen = true;
    },

    closeDeletionRequestDialog() {
        this.deleteRequestOpen = false;
        this.deleteRequestReason = '';
        this.deleteRequestAction = '';
    },

    async submitDeletionRequest(event) {
        await this.submitParticipationAction(event, () => this.closeDeletionRequestDialog());
    },

    openDeleteConfirmDialog(action) {
        this.deleteConfirmAction = action;
        this.deleteConfirmOpen = true;
    },

    closeDeleteConfirmDialog() {
        this.deleteConfirmOpen = false;
        this.deleteConfirmAction = '';
    },

    async submitDeleteConfirm(event) {
        await this.submitParticipationAction(event, () => this.closeDeleteConfirmDialog());
    },

    openReportDialog(action) {
        this.reportAction = action;
        this.reportCategory = 'record_correction';
        this.reportExplanation = '';
        this.reportOpen = true;
    },

    closeReportDialog() {
        this.reportOpen = false;
        this.reportAction = '';
        this.reportCategory = 'record_correction';
        this.reportExplanation = '';
    },

    async submitReport(event) {
        await this.submitParticipationAction(event, () => this.closeReportDialog());
    },

    async searchTournaments() {
        this.selectedTournament = null;
        if (this.tournamentQuery.length < 3) {
            this.tournamentResults = [];
            this.tournamentLoading = false;
            return;
        }
        this.tournamentLoading = true;
        try {
            const response = await fetch(`${this.tournamentSearchUrl}?q=${encodeURIComponent(this.tournamentQuery)}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            this.tournamentResults = data.tournaments || [];
        } finally {
            this.tournamentLoading = false;
        }
    },

    selectTournament(tournament) {
        if (tournament.existing_record) {
            this.openEdit(tournament.existing_record, tournament);
            return;
        }

        this.selectedTournament = tournament;
        this.tournamentDetails[tournament.id] = tournament;
        this.tournamentQuery = tournament.title;
        this.tournamentResults = [];
        this.stageValue = '';
        this.placement = '';
        this.placementPlaceholder = '';
        this.placementEditable = false;
        this.placementRangeEditable = false;
        this.structuredLocked = false;
        this.memberLocked = false;
        this.seed = '';
        this.teamName = '';
        this.selectedTeammates = [];
        this.pendingTeammateOsuIds = [];
        this.pendingOsuId = '';
        this.manualTeammateInputOpen = false;
        this.matches = [];
        this.dialogError = '';
        this.resetLobbySearchSession();
    },

    clearTournament() {
        this.selectedTournament = null;
        this.tournamentQuery = '';
        this.tournamentResults = [];
        this.resetLobbySearchSession();
    },

    updatePlacement() {
        const option = (this.selectedTournament?.stage_options || []).find((stage) => stage.value === this.stageValue);
        if (!option) {
            this.placementRangeEditable = false;
            this.placementEditable = false;
            this.placement = '';
            this.placementPlaceholder = '';
            return;
        }

        this.placementRangeEditable = option ? !!option.range_placement : false;
        this.placementEditable = option.stage_type === 'qualifier' ? false : !!option.editable_placement;
        this.placementPlaceholder = '';
        if (this.placementRangeEditable) {
            this.placement = '';
            this.placementEditable = false;
            this.placementPlaceholder = option?.placement_min ? `${option.placement_min}-` : '17-32';
            return;
        }
        if (this.placementEditable && option?.placement_min && option?.placement_max) {
            this.placement = '';
            this.placementPlaceholder = option.placement_min === option.placement_max ? `${option.placement_min}` : `${option.placement_min}-${option.placement_max}`;
            return;
        }
        if (option?.placement_min && option?.placement_max) {
            this.placement = '';
            this.placementPlaceholder = option.placement_min === option.placement_max ? `${option.placement_min}` : `${option.placement_min}-${option.placement_max}`;
            return;
        }
        if ((option?.allowed_placements || []).length > 0) {
            this.placement = option.allowed_placements.includes(Number(this.placement)) ? this.placement : '';
            return;
        }
        const parts = this.stageValue.split(':');
        if (parts[0] !== 'bracket') {
            this.placement = '';
            return;
        }
        const size = Number(parts[2] || 0);
        if (!size) {
            this.placement = '';
            return;
        }
        if (size === 2) {
            this.placement = '';
            this.placementPlaceholder = parts[3] === 'winners' ? '2' : '3';
            return;
        }
        this.placement = '';
        this.placementPlaceholder = String(Math.floor(size / 2) + 1);
    },

    async searchUsers() {
        if (this.teammateQuery.length < 2) {
            this.teammateResults = [];
            this.teammateLoading = false;
            return;
        }
        const tournamentId = this.selectedTournament?.id ? `&tournament_id=${this.selectedTournament.id}` : '';
        this.teammateLoading = true;
        try {
            const response = await fetch(`${this.userSearchUrl}?q=${encodeURIComponent(this.teammateQuery)}${tournamentId}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            this.teammateResults = data.users || [];
        } finally {
            this.teammateLoading = false;
        }
    },

    addTeammate(teammate) {
        if (!this.selectedTeammates.some((item) => item.id === teammate.id)) {
            this.selectedTeammates.push(teammate);
        }
        this.teammateQuery = '';
        this.teammateResults = [];
        this.manualTeammateInputOpen = false;
    },

    removeTeammate(id) {
        this.selectedTeammates = this.selectedTeammates.filter((teammate) => teammate.id !== id);
    },

    addPendingTeammate() {
        const osuId = Number(this.pendingOsuId || this.teammateQuery);
        if (osuId > 0 && !this.pendingTeammateOsuIds.includes(osuId)) {
            this.pendingTeammateOsuIds.push(osuId);
        }
        this.pendingOsuId = '';
        this.teammateQuery = '';
        this.teammateResults = [];
        this.manualTeammateInputOpen = false;
    },

    removePendingTeammate(osuId) {
        this.pendingTeammateOsuIds = this.pendingTeammateOsuIds.filter((value) => value !== osuId);
    },

    openPendingTeammateInput() {
        this.manualTeammateInputOpen = true;
    },

    showPendingTeammateInput() {
        return !this.memberLocked && this.manualTeammateInputOpen;
    },

    addMatch() {
        this.matches.push({ key: crypto.randomUUID(), stage: '', score_for: '', score_against: '', mp_link: '', is_forfeit: false, is_individual_qualifier: false });
    },

    removeMatch(index) {
        if (this.matches[index]?.key === this.lobbySearchOpenKey) {
            this.closeLobbySearch();
        }
        this.matches.splice(index, 1);
    },

    moveMatch(fromIndex, toIndex) {
        if (toIndex < 0 || toIndex >= this.matches.length || fromIndex === toIndex) return;

        const [match] = this.matches.splice(fromIndex, 1);
        this.matches.splice(toIndex, 0, match);
    },

    startMatchDrag(index) {
        this.draggingMatchIndex = index;
    },

    dropMatch(index) {
        if (this.draggingMatchIndex === null) return;

        this.moveMatch(this.draggingMatchIndex, index);
        this.draggingMatchIndex = null;
    },

    setForfeit(match, result) {
        if (this.isNonVersusMatch(match)) return;
        match.is_forfeit = true;
        match.score_for = result === 'win' ? 0 : -1;
        match.score_against = result === 'win' ? -1 : 0;
    },

    isQualifierMatch(match) {
        return this.canonicalMatchStage(match.stage || '') === 'Qualifier';
    },

    isNonVersusMatch(match) {
        return ['QL', 'Qualifier', 'Battle Royale', 'Tryout'].includes(match.stage);
    },

    matchStageOptions(match) {
        const options = [...(this.selectedTournament?.match_stage_options || [])];
        const stage = this.canonicalMatchStage(match.stage || '');

        if (stage && !options.includes(stage)) {
            options.unshift(stage);
        }

        return options;
    },

    normalizeMatchStage(match) {
        match.stage = this.canonicalMatchStage(match.stage || '');
        if (!this.isQualifierMatch(match)) {
            match.is_individual_qualifier = false;
        }
        if (!this.isNonVersusMatch(match)) return;

        match.score_for = '';
        match.score_against = '';
        match.is_forfeit = false;
    },

    matchFromExisting(match) {
        const scores = (match.result || '').split('-');
        return {
            key: crypto.randomUUID(),
            stage: this.canonicalMatchStage(match.stage || ''),
            score_for: match.score_for ?? (scores[0] ?? ''),
            score_against: match.score_against ?? (scores[1] ?? ''),
            mp_link: this.displayMpId(match.mp_id, match.mp_link),
            is_forfeit: !!match.is_forfeit,
            is_individual_qualifier: !!match.is_individual_qualifier,
        };
    },

    displayMpId(mpId, mpLink = '') {
        if (mpId) return String(mpId);

        const value = String(mpLink || '').trim();
        if (/^\d+$/.test(value)) return value;

        const match = value.match(/^https?:\/\/osu\.ppy\.sh\/(?:community\/matches|mp)\/(\d+)$/);

        return match?.[1] || value;
    },

    normalizeScoreInput(match, field, event, shouldFocusNext = false) {
        const normalized = this.normalizedMatchScore(event.target.value);
        match[field] = normalized;
        match.is_forfeit = match.score_for === '-1' || match.score_against === '-1';
        event.target.value = normalized;

        if (shouldFocusNext && normalized !== '' && normalized !== '-') {
            this.$nextTick(() => {
                event.target.closest('[data-match-row]')?.querySelector('[data-score-against]')?.focus();
            });
        }
    },

    finalizeScoreInput(match, field, event) {
        if (match[field] === '-') {
            match[field] = '';
            match.is_forfeit = match.score_for === '-1' || match.score_against === '-1';
            event.target.value = '';
        }
    },

    normalizedMatchScore(value) {
        const raw = String(value || '').trim();

        if (raw === '') {
            return '';
        }

        if (raw.startsWith('-')) {
            return raw === '-1' ? '-1' : '-';
        }

        const digit = raw.match(/[0-9]/)?.[0] || '';
        if (digit === '') {
            return '';
        }

        return String(Math.min(9, Number(digit)));
    },

    openLobbySearch(match, toggle = false) {
        if (toggle && this.lobbySearchOpenKey === match.key) {
            this.closeLobbySearch();
            return;
        }

        if (!toggle && match.mp_link) {
            return;
        }

        this.lobbySearchOpenKey = match.key;
    },

    closeLobbySearch() {
        this.lobbySearchOpenKey = null;
        if (this.lobbySearchTimer) {
            clearTimeout(this.lobbySearchTimer);
            this.lobbySearchTimer = null;
        }
    },

    resetLobbySearchSession() {
        this.closeLobbySearch();
        this.lobbySearchQuery = '';
        this.lobbySearchResults = [];
        this.clearLobbySearchCache();
        this.lobbySearchTotal = 0;
        this.lobbySearchOffset = 0;
        this.lobbySearchLoading = false;
        this.lobbySearchError = '';
        this.lobbySearchRequestId++;
    },

    queueLobbySearch(offset = 0) {
        if (this.lobbySearchTimer) {
            clearTimeout(this.lobbySearchTimer);
        }

        this.lobbySearchOffset = Math.max(0, offset);
        this.lobbySearchTimer = setTimeout(() => this.searchLobbies(this.lobbySearchOffset), 650);
    },

    async searchLobbies(offset = 0) {
        const query = String(this.lobbySearchQuery || '').trim();
        const datasetKey = this.lobbySearchKey(query);
        this.lobbySearchOffset = Math.max(0, offset);
        this.lobbySearchError = '';

        if (!query) {
            const openKey = this.lobbySearchOpenKey;
            this.resetLobbySearchSession();
            this.lobbySearchOpenKey = openKey;
            return;
        }

        if (this.lobbySearchLoadedComplete && this.lobbySearchDatasetKey === datasetKey) {
            this.showLobbySearchPage(this.lobbySearchOffset);
            return;
        }

        if (this.lobbySearchDatasetKey && this.lobbySearchDatasetKey !== datasetKey) {
            this.clearLobbySearchCache();
            this.lobbySearchResults = [];
            this.lobbySearchTotal = 0;
        }

        const requestId = ++this.lobbySearchRequestId;
        this.lobbySearchLoading = true;

        try {
            const params = new URLSearchParams({
                q: query,
                offset: String(this.lobbySearchOffset),
            });
            if (this.selectedTournament?.id) {
                params.set('tournament_id', String(this.selectedTournament.id));
            }
            const response = await fetch(`${this.lobbySearchUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (requestId !== this.lobbySearchRequestId) return;

            if (!response.ok) {
                const openKey = this.lobbySearchOpenKey;
                this.resetLobbySearchSession();
                this.lobbySearchOpenKey = openKey;
                this.lobbySearchError = response.status === 429
                    ? @js(__('users.participation.lobby_search.throttled'))
                    : @js(__('users.participation.lobby_search.error'));
                return;
            }

            const data = await response.json();
            this.lobbySearchLimit = Number(data.limit || 10);
            if (Array.isArray(data.all_lobbies)) {
                this.lobbySearchAllResults = data.all_lobbies;
                this.lobbySearchDatasetKey = datasetKey;
                this.lobbySearchLoadedComplete = true;
                this.showLobbySearchPage(Number(data.offset || this.lobbySearchOffset));
            } else {
                this.clearLobbySearchCache();
                this.lobbySearchResults = data.lobbies || [];
                this.lobbySearchTotal = Number(data.total || 0);
                this.lobbySearchOffset = Number(data.offset || this.lobbySearchOffset);
            }
            this.lobbySearchError = data.error || '';
        } catch (error) {
            if (requestId !== this.lobbySearchRequestId) return;

            const openKey = this.lobbySearchOpenKey;
            this.resetLobbySearchSession();
            this.lobbySearchOpenKey = openKey;
            this.lobbySearchError = @js(__('users.participation.lobby_search.error'));
        } finally {
            if (requestId === this.lobbySearchRequestId) {
                this.lobbySearchLoading = false;
            }
        }
    },

    lobbySearchKey(query = this.lobbySearchQuery) {
        return `${String(query || '').trim()}|${this.selectedTournament?.id || ''}`;
    },

    clearLobbySearchCache() {
        this.lobbySearchAllResults = [];
        this.lobbySearchDatasetKey = '';
        this.lobbySearchLoadedComplete = false;
    },

    showLobbySearchPage(offset = 0) {
        const normalizedOffset = Math.max(0, offset);
        this.lobbySearchOffset = normalizedOffset;
        this.lobbySearchTotal = this.lobbySearchAllResults.length;
        this.lobbySearchResults = this.lobbySearchAllResults.slice(
            normalizedOffset,
            normalizedOffset + this.lobbySearchLimit
        );
    },

    selectLobby(match, lobby) {
        match.mp_link = this.displayMpId(lobby.lobby_id, lobby.mp_link);
        this.closeLobbySearch();
    },

    lobbySearchHasPrevious() {
        return this.lobbySearchOffset > 0;
    },

    lobbySearchHasNext() {
        return this.lobbySearchOffset + this.lobbySearchResults.length < this.lobbySearchTotal;
    },

    previousLobbySearchPage() {
        if (!this.lobbySearchHasPrevious()) return;

        const offset = Math.max(0, this.lobbySearchOffset - this.lobbySearchLimit);
        if (this.lobbySearchLoadedComplete) {
            this.showLobbySearchPage(offset);
            return;
        }

        this.queueLobbySearch(offset);
    },

    nextLobbySearchPage() {
        if (!this.lobbySearchHasNext()) return;

        const offset = this.lobbySearchOffset + this.lobbySearchLimit;
        if (this.lobbySearchLoadedComplete) {
            this.showLobbySearchPage(offset);
            return;
        }

        this.queueLobbySearch(offset);
    },

    canonicalMatchStage(stage) {
        const value = String(stage || '').trim();
        const aliases = {
            QL: 'Qualifier',
            qualifier: 'Qualifier',
            tryout: 'Tryout',
            'battle royale': 'Battle Royale',
            'group stage': 'Group Stage',
            'swiss round': 'Swiss Round',
            qf: 'QF',
            sf: 'SF',
            f: 'F',
            gf: 'GF',
        };
        const round = value.match(/^ro(\d+)$/i);

        if (round) {
            return `Ro${round[1]}`;
        }

        return aliases[value.toLowerCase()] || value;
    },

    prepareSubmit() {
        if (!this.selectedTournament) return false;
    },

    modeLabel(mode) {
        return this.modeLabels[mode] || mode;
    },

    recordsQueryParams(offset = 0) {
        const params = new URLSearchParams();
        params.set('offset', String(offset));
        if (this.historySearch.trim() !== '') params.set('q', this.historySearch.trim());
        if (this.filterBadged) params.set('badged', '1');
        if (this.filterYear !== '') params.set('year', this.filterYear);
        if (this.filterMode !== '') params.set('mode', this.filterMode);
        if (this.focusedTournamentId) params.set('participation_tournament', this.focusedTournamentId);

        return params;
    },

    async reloadRecords() {
        await this.fetchRecords(0, true);
    },

    async loadMoreRecords() {
        if (!this.hasMoreRecords || this.recordsLoading || this.recordOffset === null) return;

        await this.fetchRecords(this.recordOffset, false);
    },

    async fetchRecords(offset, replace) {
        this.recordsLoading = true;
        try {
            const response = await fetch(`${this.recordsUrl}?${this.recordsQueryParams(offset).toString()}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            const list = this.$root.querySelector('[data-participation-record-list]');
            if (!list) return;

            if (replace) {
                list.innerHTML = data.html || '';
            } else if (data.html) {
                list.insertAdjacentHTML('beforeend', data.html);
            }

            this.recordOffset = data.next_offset;
            this.hasMoreRecords = !!data.has_more;
            this.$nextTick(() => {
                if (window.Alpine?.initTree) {
                    window.Alpine.initTree(list);
                }
            });
        } finally {
            this.recordsLoading = false;
        }
    },

    modeBadgeClass(mode) {
        return {
            'bg-pink-500/10 text-pink-400': mode === 'osu',
            'bg-cyan-500/10 text-cyan-400': mode === 'taiko',
            'bg-green-500/10 text-green-400': mode === 'catch' || mode === 'fruits',
            'bg-purple-500/10 text-purple-400': mode === 'mania',
        };
    },
});
</script>
@endif

<div class="space-y-6"
     @if($includeParticipationShell ?? true)
     x-data="window.participationDialog({
        userId: {{ $user->id }},
        storeUrl: @js(route('users.participation.store', $user)),
        tournamentSearchUrl: @js(route('users.participation.tournaments.search', $user)),
        userSearchUrl: @js(route('users.participation.users.search', $user)),
        lobbySearchUrl: @js(route('users.participation.lobbies.search', $user)),
        recordsUrl: @js($recordsUrl ?? route('users.participation.records', $user)),
        recordsNextOffset: @js($recordsNextOffset ?? null),
        recordsHasMore: @js($recordsHasMore ?? false),
        tournamentDetails: @js($tournamentDetails),
        textLocked: @js($isOwnProfile && (bool) $user->participationInputLock),
        isOwnProfile: @js($isOwnProfile),
        participationTutorialKey: 'tourney-method-participation-add-tutorial:v1',
        csrf: @js(csrf_token())
        ,focusedTournamentId: @js($focusedParticipationTournamentId ?? request()->integer('participation_tournament') ?: null)
     })"
     @endif>
    <div data-participation-refresh>
    <div class="grid gap-3 lg:grid-cols-4">
        @foreach($statGroups as $groupKey => $groupStats)
            <section class="rounded-lg border border-slate-700/50 bg-slate-800/50 p-4">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('users.participation.stats.groups.'.$groupKey) }}</h2>
                <div class="mt-3 space-y-3">
                    @foreach($groupStats as $statKey)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm text-slate-400">{{ __('users.participation.stats.'.$statKey) }}</span>
                            <span class="@if($groupKey === 'podium') text-lg font-bold {{ $podiumStatClasses[$statKey] }} @else text-lg font-bold text-white @endif">
                                @if(in_array($statKey, ['win_rate', 'top_3_rate'], true))
                                    {{ $stats[$statKey] }}%
                                @elseif($statKey === 'most_teamed_user' && ! empty($stats[$statKey]))
                                    <a href="{{ route('users.show', $stats[$statKey]['id']) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-white transition hover:text-osu-pink">
                                        <img src="https://a.ppy.sh/{{ $stats[$statKey]['osu_id'] }}" alt="{{ $stats[$statKey]['username'] }}" class="h-7 w-7 rounded-md object-cover">
                                        <span>{{ $stats[$statKey]['username'] }}</span>
                                    </a>
                                @elseif($statKey === 'most_common_country' && ! empty($stats[$statKey]))
                                    <span class="inline-flex items-center gap-2">
                                        <img src="https://flagcdn.com/w40/{{ strtolower($stats[$statKey]) }}.png" alt="{{ $stats[$statKey] }}" title="{{ $stats[$statKey] }}" class="h-4 w-6 rounded-sm object-cover">
                                        {{ $stats[$statKey] }}
                                    </span>
                                @else
                                    {{ $stats[$statKey] ?? ($statKey === 'most_common_country' ? 'N/A' : 0) }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <div class="mt-8 rounded-xl border border-slate-700/50 bg-slate-900/80 p-4 shadow-lg shadow-black/10">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <label class="relative min-w-0 flex-1">
                <x-icon name="lucide-search" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" />
                <input x-model="historySearch" @input.debounce.350ms="reloadRecords" placeholder="{{ __('users.participation.filters.search') }}" class="w-full rounded-lg border border-dark-700 bg-dark-800 py-3 pl-10 pr-3 text-sm text-gray-300 placeholder-gray-500 transition-all focus:border-osu-pink focus:outline-none focus:ring-2 focus:ring-osu-pink/20">
            </label>
            @if($isOwnProfile)
                @if($user->participationInputLock)
                    <div class="rounded-lg border border-yellow-400/40 bg-yellow-500/10 px-4 py-3 text-sm font-semibold text-yellow-200">
                        {{ __('users.participation.input_locked') }}
                    </div>
                @else
                    <div class="relative">
                        <button type="button" @click="openCreate" class="rounded-lg bg-osu-pink px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-osu-pink/20 transition hover:bg-osu-pink/80">
                            {{ __('users.participation.actions.add') }}
                        </button>
                        <div x-show="tutorialOpen" x-transition class="absolute right-0 z-30 mt-3 w-72 rounded-lg border border-pink-400/40 bg-slate-950 p-4 text-left shadow-xl shadow-black/30" style="display: none;">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-white">{{ __('users.participation.tutorial.title') }}</h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-300">{{ __('users.participation.tutorial.body') }}</p>
                                </div>
                                <button type="button" @click="dismissTutorial" class="rounded p-1 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="{{ __('users.participation.tutorial.dismiss') }}">
                                    <x-icon name="lucide-x" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <div class="mt-5 pt-5">
            <h3 class="mb-3 text-sm font-semibold text-gray-300">{{ __('users.participation.filters.mode') }}</h3>
            <div class="flex flex-wrap gap-4">
                <label class="group cursor-pointer">
                    <input type="radio" name="participation-mode" value="" x-model="filterMode" @change="reloadRecords" class="hidden peer">
                    <span class="text-gray-400 group-hover:text-white peer-checked:font-semibold peer-checked:text-osu-pink peer-checked:underline">{{ __('users.participation.filters.all_modes') }}</span>
                </label>
                @foreach($availableModes as $mode)
                    <label class="group cursor-pointer">
                        <input type="radio" name="participation-mode" value="{{ $mode }}" x-model="filterMode" @change="reloadRecords" class="hidden peer">
                        <span class="text-gray-400 group-hover:text-white peer-checked:font-semibold peer-checked:text-osu-pink peer-checked:underline" x-text="modeLabel(@js($mode))"></span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="mt-5 flex flex-col gap-4 border-t border-dark-700 pt-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h3 class="mb-3 text-sm font-semibold text-gray-300">{{ __('users.participation.filters.options') }}</h3>
                <label class="group cursor-pointer">
                    <input type="checkbox" x-model="filterBadged" @change="reloadRecords" class="hidden peer">
                    <span class="text-gray-400 group-hover:text-white peer-checked:font-semibold peer-checked:text-osu-pink peer-checked:underline">{{ __('users.participation.filters.badged') }}</span>
                </label>
            </div>
            <label class="sm:w-44">
                <span class="mb-2 block text-sm font-medium text-gray-300">{{ __('users.participation.filters.year') }}</span>
                <select x-model="filterYear" @change="reloadRecords" class="w-full rounded-lg border border-dark-700 bg-dark-800 px-4 py-2 text-sm text-gray-300 transition-all hover:border-osu-pink/50 focus:border-osu-pink focus:outline-none focus:ring-2 focus:ring-osu-pink/20">
                    <option value="">{{ __('users.participation.filters.all_years') }}</option>
                    @foreach($availableYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    @if($records->isNotEmpty())
        <div class="mt-8 rounded-xl border border-slate-700/50 bg-slate-800/50">
            <div class="border-b border-slate-700/50 px-5 py-4">
                <h2 class="font-display text-lg font-semibold text-white">{{ __('users.participation.history_title') }}</h2>
            </div>
            <div class="divide-y divide-slate-700/50" data-participation-record-list>
                @include('users.partials.participation-record-items', [
                    'records' => $records,
                    'user' => $user,
                    'isOwnProfile' => $isOwnProfile,
                    'canManageParticipation' => $canManageParticipation,
                    'stageOptions' => $stageOptions,
                    'tournamentDetails' => $tournamentDetails,
                    'participationIndices' => $participationIndices,
                    'displayTeammatesByRecord' => $displayTeammatesByRecord,
                    'displayTeammateBwsRanksByRecord' => $displayTeammateBwsRanksByRecord,
                ])
            </div>
            <div class="border-t border-slate-700/50 px-5 py-4 text-center">
                <button x-show="hasMoreRecords" type="button" @click="loadMoreRecords" :disabled="recordsLoading" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-osu-pink/50 hover:text-osu-pink disabled:cursor-not-allowed disabled:opacity-50">
                    <span x-show="!recordsLoading">Load more</span>
                    <span x-show="recordsLoading">Loading...</span>
                </button>
            </div>
        </div>
    @endif

    @if($records->isEmpty())
        <div class="rounded-xl border border-slate-700/50 bg-slate-800/30 p-12 text-center">
            <h3 class="font-display text-lg font-medium text-slate-300">{{ __('users.participation.empty_title') }}</h3>
            <p class="mt-2 text-sm text-slate-500">{{ __('users.participation.empty_desc') }}</p>
        </div>
    @endif
    </div>

    @if($canManageParticipation || auth()->check())
        <div x-show="open" class="relative z-[70]" style="display: none;" @keydown.escape.window="close">
            <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="close"></div>
            <div x-show="open" x-transition class="fixed inset-0 z-[70] overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <form method="POST" :action="formAction" @submit.prevent="submitParticipationForm" @keydown.enter="if ($event.target.tagName !== 'TEXTAREA') $event.preventDefault()" class="relative w-full max-w-3xl overflow-hidden rounded-2xl border border-dark-700 bg-dark-850 shadow-2xl" autocomplete="off" @click.stop>
                        @csrf
                        <template x-if="mode === 'edit'">
                            <input type="hidden" name="_method" value="PATCH">
                        </template>
                        <input type="hidden" name="tournament_id" :value="selectedTournament?.id || ''">
                        <input type="hidden" name="teammates_submitted" value="1" :disabled="memberLocked">
                        <template x-for="id in (memberLocked ? [] : selectedTeammates.map((teammate) => teammate.id))" :key="`known-${id}`">
                            <input type="hidden" name="teammate_ids[]" :value="id">
                        </template>
                        <template x-for="osuId in (memberLocked ? [] : pendingTeammateOsuIds)" :key="`pending-${osuId}`">
                            <input type="hidden" name="pending_teammate_osu_ids[]" :value="osuId">
                        </template>

                        <div class="flex items-center justify-between rounded-t-2xl border-b border-dark-700/50 bg-dark-850 px-5 py-4">
                            <h2 class="font-display text-xl font-semibold text-white" x-text="mode === 'edit' ? @js(__('users.participation.edit_title')) : @js(__('users.participation.add_title'))"></h2>
                            <button type="button" @click="close" class="rounded-lg p-2 text-gray-400 transition hover:bg-dark-700 hover:text-white">
                                <x-icon name="lucide-x" class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="max-h-[75vh] space-y-5 overflow-y-auto p-5">
                            <div x-show="dialogError" class="rounded-lg border border-red-400/50 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-100" x-text="dialogError"></div>
                            <section class="rounded-xl border border-dark-700/60 bg-dark-900/20 p-4">
                                <label class="mb-2 flex items-center gap-2 text-sm font-medium text-slate-300">
                                    {{ __('users.participation.fields.tournament') }}
                                    <x-field-tooltip :text="__('users.participation.tooltips.tournament')" />
                                </label>
                                <div x-show="!selectedTournament" class="relative">
                                    <x-icon name="lucide-search" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" />
                                    <input x-model="tournamentQuery"
                                           @input.debounce.250ms="searchTournaments"
                                           :disabled="mode === 'edit'"
                                           placeholder="{{ __('users.participation.fields.search_tournament') }}"
                                           autocomplete="off"
                                           class="w-full rounded-lg border border-dark-700 bg-dark-900/50 py-2.5 pl-10 pr-12 text-white placeholder-gray-500 transition-all focus:border-osu-pink/50 focus:outline-none focus:ring-2 focus:ring-osu-pink/20">
                                    <div x-show="tournamentLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <x-icon name="lucide-loader-circle" class="h-5 w-5 animate-spin text-osu-pink" />
                                    </div>
                                    <div x-show="tournamentResults.length > 0 && !selectedTournament" class="mt-2 max-h-[46vh] w-full space-y-2 overflow-y-auto rounded-xl border border-dark-700 bg-dark-900 p-2 shadow-xl">
                                        <template x-for="tournament in tournamentResults" :key="tournament.id">
                                            <button type="button" @click="selectTournament(tournament)" class="group flex w-full items-center justify-between rounded-lg border border-dark-700/50 bg-dark-900/30 p-3 text-left transition hover:border-osu-pink/50 hover:bg-dark-800/50">
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate font-tournament font-semibold text-white transition group-hover:text-osu-pink" x-text="tournament.title"></span>
                                                    <span class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-400">
                                                        <span x-text="tournament.year || 'N/A'"></span>
                                                        <span class="text-slate-500">&bull;</span>
                                                        <template x-for="mode in tournament.modes" :key="mode">
                                                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium" :class="modeBadgeClass(mode)" x-text="modeLabel(mode)"></span>
                                                        </template>
                                                        <template x-if="tournament.is_badge">
                                                            <span class="inline-flex items-center rounded-md bg-yellow-500/10 px-2 py-0.5 text-xs font-medium text-yellow-300">{{ __('users.history.badged') }}</span>
                                                        </template>
                                                    </span>
                                                </span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <template x-if="selectedTournament">
                                    <div class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-dark-700/50 bg-dark-900/30 p-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate font-tournament font-semibold text-white" x-text="selectedTournament.title"></div>
                                            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-400">
                                                <span x-text="selectedTournament.year || 'N/A'"></span>
                                                <span class="text-slate-500">&bull;</span>
                                                <template x-for="mode in selectedTournament.modes" :key="mode">
                                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium" :class="modeBadgeClass(mode)" x-text="modeLabel(mode)"></span>
                                                </template>
                                                <template x-if="selectedTournament.is_badge">
                                                    <span class="inline-flex items-center rounded-md bg-yellow-500/10 px-2 py-0.5 text-xs font-medium text-yellow-300">{{ __('users.history.badged') }}</span>
                                                </template>
                                            </div>
                                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                                <a :href="selectedTournament.profile_url" target="_blank" rel="noopener" class="rounded border border-slate-700 px-2 py-1 font-semibold text-slate-300 hover:border-pink-400 hover:text-pink-300">{{ __('users.participation.links.details') }}</a>
                                                <template x-if="selectedTournament.forum_post_url">
                                                    <a :href="selectedTournament.forum_post_url" target="_blank" rel="noopener" class="rounded border border-slate-700 px-2 py-1 font-semibold text-slate-300 hover:border-pink-400 hover:text-pink-300">{{ __('users.participation.links.forum') }}</a>
                                                </template>
                                                <template x-if="selectedTournament.spreadsheet_url">
                                                    <a :href="selectedTournament.spreadsheet_url" target="_blank" rel="noopener" class="rounded border border-slate-700 px-2 py-1 font-semibold text-slate-300 hover:border-green-400 hover:text-green-300">{{ __('users.participation.links.sheet') }}</a>
                                                </template>
                                                <template x-if="selectedTournament.bracket_url">
                                                    <a :href="selectedTournament.bracket_url" target="_blank" rel="noopener" class="rounded border border-slate-700 px-2 py-1 font-semibold text-slate-300 hover:border-cyan-400 hover:text-cyan-300">{{ __('users.participation.links.bracket') }}</a>
                                                </template>
                                            </div>
                                        </div>
                                        <button x-show="mode !== 'edit'" type="button" @click="clearTournament" class="text-sm font-semibold text-osu-pink hover:text-pink-300">{{ __('users.participation.actions.change') }}</button>
                                    </div>
                                </template>
                            </section>

                            <template x-if="selectedTournament">
                                <div class="space-y-5">
                                    <section class="rounded-xl border border-dark-700/60 bg-dark-900/20 p-4">
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <label>
                                            <span class="mb-1 flex items-center gap-2 text-sm font-medium text-slate-300">
                                                {{ __('users.participation.fields.finished_stage') }}
                                                <x-field-tooltip>
                                                    <span class="block">{{ __('users.participation.tooltips.finished_stage') }}</span>
                                                    <span class="mt-2 block">{{ __('users.participation.final_result_statuses.dnq') }}</span>
                                                    <span class="block">{{ __('users.participation.final_result_statuses.dnp') }}</span>
                                                    <span class="block">{{ __('users.participation.final_result_statuses.tryout') }}</span>
                                                </x-field-tooltip>
                                            </span>
                                            <select :name="structuredLocked ? '' : 'stage_value'" :disabled="structuredLocked" x-model="stageValue" @change="updatePlacement" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white disabled:text-slate-400">
                                                <option value="">{{ __('users.participation.fields.select_stage') }}</option>
                                                <template x-for="option in selectedTournament.stage_options" :key="option.value">
                                                    <option :value="option.value" x-text="option.label"></option>
                                                </template>
                                            </select>
                                        </label>
                                        <label>
                                            <span class="mb-1 flex items-center gap-2 text-sm font-medium text-slate-300">
                                                {{ __('users.participation.fields.placement') }}
                                                <x-field-tooltip>
                                                    <span class="block">{{ __('users.participation.tooltips.final_result') }}</span>
                                                </x-field-tooltip>
                                            </span>
                                            <input type="text" inputmode="numeric" :name="placementRangeEditable && !structuredLocked ? 'placement_range_override' : (placementEditable && !structuredLocked ? 'placement_override' : '')" :disabled="(!placementEditable && !placementRangeEditable) || structuredLocked" x-model="placement" :placeholder="placementPlaceholder" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white placeholder-slate-500 disabled:text-slate-400">
                                        </label>
                                        <label x-show="selectedTournament.has_qualifier">
                                            <span class="mb-1 flex items-center gap-2 text-sm font-medium text-slate-300">
                                                {{ __('users.participation.fields.seed') }}
                                                <x-field-tooltip :text="__('users.participation.tooltips.seed')" />
                                            </span>
                                            <span class="flex items-center gap-2">
                                                <input type="number" min="1" name="seed" x-model="seed" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white disabled:text-slate-400">
                                                <span class="text-sm text-slate-400" x-show="selectedTournament.qualifier_cutoff" x-text="`/${selectedTournament.qualifier_cutoff}`"></span>
                                            </span>
                                        </label>
                                    </div>
                                    </section>

                                    <section x-show="selectedTournament.is_team_tournament" class="rounded-xl border border-dark-700/60 bg-dark-900/20 p-4">
                                        <label x-show="!textLocked" class="mb-4 block">
                                            <span class="mb-1 flex items-center gap-2 text-sm font-medium text-slate-300">
                                                {{ __('users.participation.fields.team_name') }}
                                                <x-field-tooltip :text="__('users.participation.tooltips.team_name')" />
                                            </span>
                                            <input name="team_name" x-model="teamName" maxlength="150" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                                        </label>
                                        <label class="mb-2 flex items-center gap-2 text-sm font-medium text-slate-300">
                                            {{ __('users.participation.fields.teammates') }}
                                            <x-field-tooltip :text="__('users.participation.tooltips.teammates')" />
                                        </label>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="teammate in selectedTeammates" :key="teammate.id">
                                                <span class="inline-flex items-center gap-2 rounded-lg border border-dark-700/70 bg-dark-900/50 px-2 py-1 text-sm text-white">
                                                    <a :href="teammate.profile_url || `/users/${teammate.id}`" target="_blank" rel="noopener" class="inline-flex items-center gap-2 transition hover:text-osu-pink">
                                                        <span class="relative">
                                                            <img :src="`https://a.ppy.sh/${teammate.osu_id}`" :alt="teammate.username" class="h-7 w-7 rounded-md object-cover">
                                                            <template x-if="teammate.country_code">
                                                                <img :src="`https://flagcdn.com/w40/${teammate.country_code.toLowerCase()}.png`" :alt="teammate.country_code" :title="teammate.country_code" class="absolute -bottom-1 -right-1 h-3 w-5 rounded-sm border border-dark-900 object-cover">
                                                            </template>
                                                        </span>
                                                        <span x-text="teammate.username"></span>
                                                    </a>
                                                    <button x-show="!memberLocked" type="button" @click="removeTeammate(teammate.id)" class="text-slate-400 hover:text-white">x</button>
                                                </span>
                                            </template>
                                            <template x-for="osuId in pendingTeammateOsuIds" :key="osuId">
                                                <span class="inline-flex items-center gap-2 rounded-full bg-pink-500/15 px-3 py-1 text-sm text-pink-200">
                                                    <span x-text="`osu ${osuId}`"></span>
                                                    <button x-show="!memberLocked" type="button" @click="removePendingTeammate(osuId)" class="text-pink-200 hover:text-white">x</button>
                                                </span>
                                            </template>
                                        </div>
                                        <div x-show="!memberLocked" class="relative mt-3">
                                            <x-icon name="lucide-search" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" />
                                           <input x-model="teammateQuery"
                                                   @input.debounce.250ms="searchUsers"
                                                   placeholder="{{ __('users.participation.fields.search_teammate') }}"
                                                   autocomplete="off"
                                                   class="w-full rounded-lg border border-dark-700 bg-dark-900/50 py-2.5 pl-10 pr-12 text-white placeholder-gray-500 transition-all focus:border-osu-pink/50 focus:outline-none focus:ring-2 focus:ring-osu-pink/20">
                                            <div x-show="teammateLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                                                <x-icon name="lucide-loader-circle" class="h-5 w-5 animate-spin text-osu-pink" />
                                            </div>
                                            <div x-show="!teammateLoading && teammateQuery.length >= 2 && !manualTeammateInputOpen" class="absolute z-30 mt-2 max-h-64 w-full space-y-2 overflow-y-auto rounded-xl border border-dark-700 bg-dark-900 p-2 shadow-xl">
                                                <template x-for="teammate in teammateResults" :key="teammate.id">
                                                    <button type="button" @click="addTeammate(teammate)" class="group flex w-full items-center gap-3 rounded-lg border border-dark-700/50 bg-dark-900/30 p-3 text-left transition hover:border-osu-cyan/50 hover:bg-dark-800/50">
                                                        <span class="relative flex-shrink-0">
                                                            <img :src="teammate.avatar_url || `https://a.ppy.sh/${teammate.osu_id}`" :alt="teammate.username" class="h-10 w-10 rounded-lg object-cover ring-2 ring-dark-700 transition group-hover:ring-osu-cyan/30">
                                                            <template x-if="teammate.country_code">
                                                                <img :src="`https://flagcdn.com/w40/${teammate.country_code.toLowerCase()}.png`" :alt="teammate.country_code" :title="teammate.country_code" class="absolute -bottom-1 -right-1 h-3 w-5 rounded-sm border border-dark-900 object-cover">
                                                            </template>
                                                        </span>
                                                        <span class="min-w-0 flex-1">
                                                            <span class="block truncate font-display font-semibold text-white transition group-hover:text-osu-cyan" x-text="teammate.username"></span>
                                                            <template x-if="teammate.main_mode">
                                                                <span class="mt-1 inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium" :class="modeBadgeClass(teammate.main_mode)" x-text="modeLabel(teammate.main_mode)"></span>
                                                            </template>
                                                        </span>
                                                    </button>
                                                </template>
                                                <div x-show="teammateResults.length === 0" class="px-8 py-8 text-center">
                                                    <p class="text-gray-500">
                                                    {{ __('users.participation.no_teammate_results') }}
                                                    </p>
                                                </div>
                                                <button type="button" @click="openPendingTeammateInput" class="block w-full text-center text-sm font-medium text-osu-pink transition-opacity hover:opacity-80">
                                                    {{ __('users.participation.add_teammate_manual') }}
                                                </button>
                                            </div>
                                        </div>
                                        <div x-show="showPendingTeammateInput()" class="mt-2 flex gap-2">
                                            <label class="flex-1">
                                                <span class="sr-only">{{ __('users.participation.fields.pending_teammate_osu_id') }}</span>
                                                <input x-model="pendingOsuId" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" placeholder="{{ __('users.participation.fields.pending_teammate_osu_id') }}" title="{{ __('users.participation.tooltips.pending_teammate_osu_id') }}" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                                            </label>
                                            <button type="button" @click="addPendingTeammate" class="rounded-lg border border-pink-500/50 px-3 py-2 text-sm font-semibold text-pink-300">{{ __('users.participation.actions.add') }}</button>
                                        </div>
                                    </section>

                                    <section x-show="!textLocked" class="rounded-xl border border-dark-700/60 bg-dark-900/20 p-4">
                                    <label>
                                        <span class="mb-1 flex items-center gap-2 text-sm font-medium text-slate-300">
                                            {{ __('users.participation.fields.memo') }}
                                            <x-field-tooltip :text="__('users.participation.tooltips.memo')" />
                                        </span>
                                        <textarea name="memo" x-model="memo" maxlength="1000" rows="3" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white"></textarea>
                                    </label>
                                    </section>
                                    <section class="rounded-xl border border-dark-700/60 bg-dark-900/20 p-4">
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="flex items-center gap-2 text-sm font-medium text-slate-300">
                                                {{ __('users.participation.fields.matches') }}
                                                <x-field-tooltip :text="__('users.participation.tooltips.matches')" />
                                            </span>
                                            <button type="button" @click="addMatch" class="rounded-lg border border-slate-700 px-3 py-1.5 text-sm font-semibold text-slate-200 hover:bg-slate-800">{{ __('users.participation.actions.add_match') }}</button>
                                        </div>
                                        <div x-show="matches.length === 0" class="rounded-lg border border-dashed border-slate-700 p-4 text-center text-sm text-slate-500">{{ __('users.participation.empty_matches') }}</div>
                                        <template x-for="(match, index) in matches" :key="match.key">
                                            <div class="mb-2 rounded-lg border border-slate-700/60 bg-slate-900/50 p-3 transition"
                                                 data-match-row
                                                 @dragover.prevent
                                                 @drop.prevent="dropMatch(index)"
                                                 :class="draggingMatchIndex === index ? 'border-osu-pink/50 opacity-70' : ''">
                                                <div class="flex flex-wrap items-center gap-2">
                                                <button type="button" draggable="true" @dragstart="startMatchDrag(index)" @dragend="draggingMatchIndex = null" class="inline-flex h-9 w-9 flex-none cursor-grab items-center justify-center rounded-lg border border-slate-700 bg-slate-950 text-slate-400 active:cursor-grabbing" title="Drag to reorder">
                                                    <x-icon name="lucide-ellipsis-vertical" class="h-4 w-4" />
                                                </button>
                                                <select :name="`matches[${index}][stage]`" x-model="match.stage" @change="normalizeMatchStage(match)" title="{{ __('users.participation.tooltips.match_stage') }}" class="w-24 flex-none rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-white">
                                                    <option value="">{{ __('users.participation.fields.match_stage') }}</option>
                                                    <template x-for="stage in matchStageOptions(match)" :key="stage">
                                                        <option :value="stage" x-text="stage"></option>
                                                    </template>
                                                </select>
                                                <div x-show="!isNonVersusMatch(match)" class="participation-score-control flex h-9 flex-none items-center overflow-hidden rounded-lg border border-slate-700 bg-slate-950 focus-within:border-osu-pink focus-within:ring-2 focus-within:ring-osu-pink/20">
                                                    <input type="text" inputmode="numeric" maxlength="2" autocomplete="off" :name="isNonVersusMatch(match) ? '' : `matches[${index}][score_for]`" x-model="match.score_for" @input="normalizeScoreInput(match, 'score_for', $event, true)" @blur="finalizeScoreInput(match, 'score_for', $event)" title="{{ __('users.participation.tooltips.score_for') }}" class="number-input-no-spinner h-full w-7 border-0 bg-transparent px-1 text-center text-sm text-white outline-none ring-0 focus:border-0 focus:outline-none focus:ring-0">
                                                    <span class="flex h-full w-3 items-center justify-center text-sm text-slate-500 select-none">-</span>
                                                    <input type="text" inputmode="numeric" maxlength="2" autocomplete="off" data-score-against :name="isNonVersusMatch(match) ? '' : `matches[${index}][score_against]`" x-model="match.score_against" @input="normalizeScoreInput(match, 'score_against', $event)" @blur="finalizeScoreInput(match, 'score_against', $event)" title="{{ __('users.participation.tooltips.score_against') }}" class="number-input-no-spinner h-full w-7 border-0 bg-transparent px-1 text-center text-sm text-white outline-none ring-0 focus:border-0 focus:outline-none focus:ring-0">
                                                </div>
                                                <div class="relative min-w-[11rem] flex-1" @click.outside="if (lobbySearchOpenKey === match.key) closeLobbySearch()" @pointerdown.window="lobbySearchOpenKey === match.key && !$el.contains($event.target) && closeLobbySearch()">
                                                    <div class="participation-mp-link-control">
                                                        <input :name="`matches[${index}][mp_link]`" x-model="match.mp_link" @focus="openLobbySearch(match, false)" placeholder="{{ __('users.participation.fields.mp_link') }}" title="{{ __('users.participation.tooltips.mp_link') }}" class="participation-mp-link-input min-w-0 flex-1 rounded-none border-0 bg-transparent px-3 py-2 text-sm text-white placeholder-slate-500">
                                                        <button type="button" @click.stop="openLobbySearch(match, true)" title="{{ __('users.participation.lobby_search.open') }}" class="inline-flex h-9 w-9 flex-none items-center justify-center rounded-r-lg border-l border-slate-700 text-slate-300 transition hover:bg-slate-800 hover:text-osu-cyan">
                                                            <span class="sr-only">{{ __('users.participation.lobby_search.open') }}</span>
                                                            <x-icon name="lucide-search" class="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                    <div x-show="lobbySearchOpenKey === match.key" x-transition class="absolute left-0 right-0 z-50 mt-2 overflow-hidden rounded-lg border border-slate-700 bg-slate-950 shadow-2xl shadow-black/40" style="display: none;">
                                                        <div class="border-b border-slate-800 p-2">
                                                            <input x-model="lobbySearchQuery" @input="queueLobbySearch(0)" placeholder="{{ __('users.participation.lobby_search.placeholder') }}" class="w-full rounded-md border border-slate-700 bg-slate-900 px-2 py-1.5 text-sm text-white placeholder-slate-500 focus:border-osu-pink focus:outline-none focus:ring-2 focus:ring-osu-pink/20">
                                                        </div>
                                                        <div class="max-h-56 overflow-y-auto">
                                                            <div x-show="lobbySearchLoading" class="px-3 py-3 text-sm text-slate-400">{{ __('users.participation.lobby_search.loading') }}</div>
                                                            <div x-show="!lobbySearchLoading && lobbySearchError" class="px-3 py-3 text-sm text-yellow-200" x-text="lobbySearchError"></div>
                                                            <div x-show="!lobbySearchLoading && !lobbySearchError && lobbySearchResults.length === 0" class="px-3 py-3 text-sm text-slate-500">{{ __('users.participation.lobby_search.empty') }}</div>
                                                            <template x-for="lobby in lobbySearchResults" :key="lobby.lobby_id">
                                                                <button type="button" @click="selectLobby(match, lobby)" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition hover:bg-slate-800">
                                                                    <span class="min-w-0 flex-1 truncate text-slate-200" x-text="lobby.lobby_name"></span>
                                                                    <span class="flex-none text-xs text-slate-500" x-text="lobby.created_at_display || ''"></span>
                                                                </button>
                                                            </template>
                                                        </div>
                                                        <div class="flex items-center justify-between border-t border-slate-800 px-2 py-1.5">
                                                            <span class="text-xs text-slate-500" x-text="lobbySearchTotal ? `${lobbySearchOffset + 1}-${lobbySearchOffset + lobbySearchResults.length} / ${lobbySearchTotal}` : ''"></span>
                                                            <div class="flex gap-1">
                                                                <button type="button" @click="previousLobbySearchPage" :disabled="!lobbySearchHasPrevious() || lobbySearchLoading" title="{{ __('users.participation.lobby_search.previous') }}" class="h-7 w-7 rounded-md text-slate-300 hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40">&lt;</button>
                                                                <button type="button" @click="nextLobbySearchPage" :disabled="!lobbySearchHasNext() || lobbySearchLoading" title="{{ __('users.participation.lobby_search.next') }}" class="h-7 w-7 rounded-md text-slate-300 hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40">&gt;</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="button" @click="moveMatch(index, index - 1)" :disabled="index === 0" class="h-9 w-9 flex-none rounded-lg p-2 text-slate-300 hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40" title="Move up">
                                                    <x-icon name="lucide-chevron-up" class="h-4 w-4" />
                                                </button>
                                                <button type="button" @click="moveMatch(index, index + 1)" :disabled="index === matches.length - 1" class="h-9 w-9 flex-none rounded-lg p-2 text-slate-300 hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40" title="Move down">
                                                    <x-icon name="lucide-chevron-down" class="h-4 w-4" />
                                                </button>
                                                <button type="button" @click="removeMatch(index)" class="h-9 w-9 flex-none rounded-lg p-2 text-red-300 hover:bg-red-500/10">x</button>
                                                </div>
                                                <div x-show="!isNonVersusMatch(match)" class="mt-2 flex gap-2">
                                                    <button type="button" @click="setForfeit(match, 'win')" title="{{ __('users.participation.tooltips.ff_win') }}" class="rounded border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:bg-slate-800">FF W</button>
                                                    <button type="button" @click="setForfeit(match, 'lose')" title="{{ __('users.participation.tooltips.ff_loss') }}" class="rounded border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:bg-slate-800">FF L</button>
                                                    <input type="hidden" :name="`matches[${index}][is_forfeit]`" :value="match.is_forfeit ? 1 : 0">
                                                </div>
                                                <label x-show="isQualifierMatch(match)" class="mt-2 inline-flex items-center gap-2 text-xs text-slate-300">
                                                    <input type="checkbox" :name="isQualifierMatch(match) ? `matches[${index}][is_individual_qualifier]` : ''" value="1" x-model="match.is_individual_qualifier" class="rounded border-slate-600 bg-slate-950 text-osu-pink focus:ring-osu-pink">
                                                    <span class="flex items-center gap-1.5">
                                                        {{ __('users.participation.fields.individual_qualifier') }}
                                                        <x-field-tooltip :text="__('users.participation.tooltips.individual_qualifier')" />
                                                    </span>
                                                </label>
                                            </div>
                                        </template>
                                    </section>
                                </div>
                            </template>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-700/70 px-5 py-4">
                            <button type="button" @click="close" class="rounded-lg border border-dark-700 px-4 py-2 font-semibold text-slate-300 transition hover:bg-dark-800">{{ __('users.participation.actions.cancel') }}</button>
                            <button :disabled="!selectedTournament || submitting" class="rounded-lg bg-osu-pink px-4 py-2 font-semibold text-white transition hover:bg-pink-400 disabled:cursor-not-allowed disabled:opacity-50">{{ __('users.participation.actions.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div x-show="deleteRequestOpen" class="relative z-[80]" style="display: none;" @keydown.escape.window="closeDeletionRequestDialog">
            <div x-show="deleteRequestOpen" x-transition.opacity class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeDeletionRequestDialog"></div>
            <div x-show="deleteRequestOpen" x-transition class="fixed inset-0 z-[80] overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <form method="POST" :action="deleteRequestAction" @submit.prevent="submitDeletionRequest" class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-dark-700 bg-dark-850 shadow-2xl" autocomplete="off" @click.stop>
                        @csrf
                        <div class="border-b border-dark-700/50 px-5 py-4">
                            <h2 class="font-display text-xl font-semibold text-white">{{ __('users.participation.delete_request_title') }}</h2>
                        </div>
                        <div class="space-y-3 p-5">
                            <label>
                                <span class="mb-1 flex items-center gap-2 text-sm font-medium text-slate-300">
                                    {{ __('users.participation.fields.deletion_reason') }}
                                    <x-field-tooltip :text="__('users.participation.tooltips.deletion_reason')" />
                                </span>
                                <textarea name="reason" x-model="deleteRequestReason" rows="4" maxlength="500" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white placeholder-slate-500 focus:border-osu-pink focus:outline-none focus:ring-2 focus:ring-osu-pink/20"></textarea>
                            </label>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-700/70 px-5 py-4">
                            <button type="button" @click="closeDeletionRequestDialog" class="rounded-lg border border-dark-700 px-4 py-2 font-semibold text-slate-300 transition hover:bg-dark-800">{{ __('users.participation.actions.cancel') }}</button>
                            <button :disabled="submitting" class="rounded-lg border border-red-400/50 px-4 py-2 font-semibold text-red-300 transition hover:bg-red-500/10 disabled:cursor-not-allowed disabled:opacity-50">{{ __('users.participation.actions.send_delete_request') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div x-show="deleteConfirmOpen" class="relative z-[80]" style="display: none;" @keydown.escape.window="closeDeleteConfirmDialog">
            <div x-show="deleteConfirmOpen" x-transition.opacity class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeDeleteConfirmDialog"></div>
            <div x-show="deleteConfirmOpen" x-transition class="fixed inset-0 z-[80] overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <form method="POST" :action="deleteConfirmAction" @submit.prevent="submitDeleteConfirm" class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-dark-700 bg-dark-850 shadow-2xl" autocomplete="off" @click.stop>
                        @csrf
                        <input type="hidden" name="_method" value="DELETE">
                        <div class="border-b border-dark-700/50 px-5 py-4">
                            <h2 class="font-display text-xl font-semibold text-white">{{ __('users.participation.delete_confirm_title') }}</h2>
                        </div>
                        <div class="p-5">
                            <p class="text-sm text-slate-300">{{ __('users.participation.confirm_delete') }}</p>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-700/70 px-5 py-4">
                            <button type="button" @click="closeDeleteConfirmDialog" class="rounded-lg border border-dark-700 px-4 py-2 font-semibold text-slate-300 transition hover:bg-dark-800">{{ __('users.participation.actions.cancel') }}</button>
                            <button :disabled="submitting" class="rounded-lg border border-red-400/50 px-4 py-2 font-semibold text-red-300 transition hover:bg-red-500/10 disabled:cursor-not-allowed disabled:opacity-50">{{ __('users.participation.actions.delete') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div x-show="reportOpen" class="relative z-[80]" style="display: none;" @keydown.escape.window="closeReportDialog">
            <div x-show="reportOpen" x-transition.opacity class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="closeReportDialog"></div>
            <div x-show="reportOpen" x-transition class="fixed inset-0 z-[80] overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <form method="POST" :action="reportAction" @submit.prevent="submitReport" class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-dark-700 bg-dark-850 shadow-2xl" autocomplete="off" @click.stop>
                        @csrf
                        <div class="border-b border-dark-700/50 px-5 py-4">
                            <h2 class="font-display text-xl font-semibold text-white">{{ __('users.participation.report.title') }}</h2>
                        </div>
                        <div class="space-y-4 p-5">
                            <label class="block">
                                <span class="mb-1 flex items-center gap-2 text-sm font-medium text-slate-300">
                                    {{ __('users.participation.report.category') }}
                                    <x-field-tooltip :text="__('users.participation.tooltips.report_category')" />
                                </span>
                                <select name="category" x-model="reportCategory" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white">
                                    <option value="record_correction">{{ __('users.participation.report.categories.record_correction') }}</option>
                                    <option value="report_spam">{{ __('users.participation.report.categories.report_spam') }}</option>
                                    <option value="inappropriate_memo">{{ __('users.participation.report.categories.inappropriate_memo') }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-1 flex items-center gap-2 text-sm font-medium text-slate-300">
                                    {{ __('users.participation.report.explanation') }}
                                    <x-field-tooltip :text="__('users.participation.tooltips.report_explanation')" />
                                </span>
                                <textarea name="explanation" x-model="reportExplanation" rows="4" maxlength="1000" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-white placeholder-slate-500 focus:border-osu-pink focus:outline-none focus:ring-2 focus:ring-osu-pink/20"></textarea>
                            </label>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-700/70 px-5 py-4">
                            <button type="button" @click="closeReportDialog" class="rounded-lg border border-dark-700 px-4 py-2 font-semibold text-slate-300 transition hover:bg-dark-800">{{ __('users.participation.actions.cancel') }}</button>
                            <button :disabled="submitting" class="rounded-lg bg-osu-pink px-4 py-2 font-semibold text-white transition hover:bg-pink-400 disabled:cursor-not-allowed disabled:opacity-50">{{ __('users.participation.actions.send_report') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
