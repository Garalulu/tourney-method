@props([
    'tournament' => null,
])

<h2 class="text-lg font-bold mb-4 flex items-center space-x-2" style="font-family: 'Outfit', sans-serif;">
    <x-icon name="lucide-file-text" class="w-5 h-5 text-[var(--osu-cyan)]" />
    <span>Parsed Forum Post Content</span>
</h2>

@if($tournament->description)
    @php
        $bbcodeParser = app(\App\Services\BbcodeParser::class);
        $markdownContent = $bbcodeParser->toMarkdown($tournament->description);
    @endphp

    <div class="bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] p-6">
        <div class="flex items-center justify-between mb-4">
            <span class="text-sm text-[var(--admin-muted)]">
                BBcode content converted to Markdown
            </span>
            <a href="{{ $tournament->forum_post_url }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-[var(--osu-cyan)] text-[var(--admin-bg)] hover:brightness-110 transition-all">
                <x-icon name="lucide-external-link" class="w-4 h-4 mr-1" />
                View Original Post
            </a>
        </div>
        <div class="prose prose-sm max-w-none
                    whitespace-pre-wrap
                    break-words
                    overflow-y-auto
                    max-h-[500px]">
            <pre class="text-sm font-mono text-[var(--admin-text)] leading-relaxed">{{ $markdownContent }}</pre>
        </div>
    </div>
@else
    <div class="bg-[var(--admin-surface)] rounded-lg border border-[var(--admin-border)] flex items-center justify-center py-20">
        <div class="text-center">
            <x-icon name="lucide-file-text" class="w-16 h-16 mx-auto mb-4 text-[var(--admin-muted)]" />
            <p class="text-[var(--admin-muted)] mb-4">No parsed forum content available</p>
            @if($tournament->forum_post_url)
                <a href="{{ $tournament->forum_post_url }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-[var(--osu-cyan)] text-[var(--admin-bg)] hover:brightness-110 transition-all">
                    <x-icon name="lucide-external-link" class="w-4 h-4 mr-2" />
                    View Original Post
                </a>
            @endif
        </div>
    </div>
@endif
