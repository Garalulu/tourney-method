@props(['tournament'])
@if($tournament->staff()->count() > 0)
    <div class="space-y-3">
        <h4 class="font-bold text-white">Staff</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($tournament->staff()->withPivot('role')->get() as $staffMember)
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-pink-400 capitalize">{{ $staffMember->pivot->role }}</span>
                    <span class="text-white">{{ $staffMember->username }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
