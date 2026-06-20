@props(['tournament'])

@php
    use App\Helpers\StaffRoleHelper;

    // Get only approved staff and group by role
    $approvedStaff = $tournament->staff->filter(function ($staffMember) {
        return ($staffMember->pivot->status ?? 'approved') === 'approved';
    });

    // Get host OSU ID from tournament
    $hostOsuId = $tournament->host_osu_id;

    $staffByRole = [];
    foreach ($approvedStaff as $staffMember) {
        $role = $staffMember->pivot->role ?? 'other';
        if (!isset($staffByRole[$role])) {
            $staffByRole[$role] = [];
        }
        $staffByRole[$role][] = $staffMember;
    }

    // Sort staff within each role: host first, then alphabetically
    foreach ($staffByRole as $role => &$staffMembers) {
        usort($staffMembers, function($a, $b) use ($hostOsuId) {
            // Host goes first
            $aIsHost = $a->osu_id === $hostOsuId;
            $bIsHost = $b->osu_id === $hostOsuId;
            
            if ($aIsHost && !$bIsHost) return -1;
            if (!$aIsHost && $bIsHost) return 1;
            
            // Both host or neither host: sort alphabetically
            return strcmp($a->username, $b->username);
        });
    }
    unset($staffMembers);

    // Role priority order
    $roleOrder = ['organizer', 'mappooler', 'playtester', 'mapper', 'gfx', 'sheeter', 'referee', 'streamer', 'commentator', 'other'];
    $totalStaff = $approvedStaff->count();
@endphp

@if($totalStaff > 0)
    <div data-tournament-section="staff" class="rounded-2xl border-2 border-slate-700/50 bg-slate-800/50 p-4 sm:p-8">
        {{-- Section Header --}}
        <div class="mb-6 flex items-center justify-between sm:mb-8">
            <div>
                <h2 class="flex items-center gap-3 text-xl font-bold text-white sm:text-2xl">
                    <div class="w-1 h-8 bg-gradient-to-b from-pink-500 to-purple-500 rounded-full"></div>
                    {{ __('tournaments.staffs.title') }}
                </h2>
            </div>
        </div>

        {{-- Staff Groups by Role --}}
        <div class="space-y-6 sm:space-y-8">
            @foreach($roleOrder as $role)
                @if(isset($staffByRole[$role]) && count($staffByRole[$role]) > 0)
                    <div>
                        {{-- Role Header --}}
                        <div class="mb-3 sm:mb-4">
                            <h3 class="flex items-center gap-2 text-base font-bold text-white sm:text-lg">
                                <span class="w-1 h-6 bg-gradient-to-b from-pink-500 to-purple-500 rounded-full"></span>
                                <span>{{ __('tournaments.staffs.' . $role) }}</span>
                                <span class="text-sm font-normal text-slate-400">
                                    ({{ count($staffByRole[$role]) }})
                                </span>
                            </h3>
                        </div>

                        {{-- Staff Grid for this role --}}
                        <div data-staff-roster-layout="mobile-two-column" class="grid grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-3">
                            @foreach($staffByRole[$role] as $staffMember)
                                <div class="group min-w-0 rounded-xl border border-slate-700/50 bg-slate-900/50 p-2 transition-all duration-300 hover:border-pink-500/30 sm:p-4">
                                    <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                                        {{-- Avatar --}}
                                        <a href="{{ route('users.show', $staffMember->id) }}"
                                           class="relative flex-shrink-0">
                                            <img
                                                src="{{ $staffMember->avatar_url ?: 'https://a.ppy.sh/'.$staffMember->osu_id }}"
                                                alt="{{ $staffMember->username }}"
                                                class="h-7 w-7 rounded-md border border-pink-500/30 object-cover transition-colors group-hover:border-pink-400 sm:h-12 sm:w-12 sm:rounded-full sm:border-2">
                                            @if($staffMember->country_code)
                                                <img src="https://flagcdn.com/w40/{{ strtolower($staffMember->country_code) }}.png"
                                                     alt="{{ $staffMember->country_code }}"
                                                     class="absolute -bottom-1 -right-1 h-3 w-5 rounded-sm border border-slate-900 object-cover sm:hidden"
                                                     title="{{ $staffMember->country_code }}">
                                            @endif
                                        </a>

                                        {{-- User Info --}}
                                        <div class="flex-1 min-w-0">
                                            <a href="{{ route('users.show', $staffMember->id) }}"
                                               class="block">
                                                <div class="font-bold text-white group-hover:text-pink-400 transition-colors truncate">
                                                    {{ $staffMember->username }}
                                                </div>
                                            </a>
                                            {{-- Country Flag --}}
                                            <div class="mt-1 hidden items-center gap-2 sm:flex">
                                                @if($staffMember->country_code)
                                                    <img src="https://flagcdn.com/w40/{{ strtolower($staffMember->country_code) }}.png"
                                                         alt="{{ $staffMember->country_code }}"
                                                         class="w-6 h-4 object-cover rounded shadow-sm"
                                                         title="{{ $staffMember->country_code }}">
                                                @else
                                                    <span class="text-slate-500 text-xs">🌍</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endif
