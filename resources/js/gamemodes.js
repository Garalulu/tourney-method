const labels = {
    osu: 'osu!',
    taiko: 'osu!taiko',
    catch: 'osu!catch',
    fruits: 'osu!catch',
    mania: 'osu!mania',
    '4k': 'osu!mania 4K',
    '7k': 'osu!mania 7K',
};

const icons = {
    osu: '/images/gamemodes/std_white.webp',
    taiko: '/images/gamemodes/taiko_white.webp',
    catch: '/images/gamemodes/catch_white.webp',
    fruits: '/images/gamemodes/catch_white.webp',
    mania: '/images/gamemodes/mania_white.webp',
    '4k': '/images/gamemodes/mania_white.webp',
    '7k': '/images/gamemodes/mania_white.webp',
};

const badgeClasses = {
    osu: 'bg-pink-500/10 text-pink-400',
    taiko: 'bg-cyan-500/10 text-cyan-400',
    catch: 'bg-green-500/10 text-green-400',
    fruits: 'bg-green-500/10 text-green-400',
    mania: 'bg-purple-500/10 text-purple-400',
    '4k': 'bg-purple-500/10 text-purple-400',
    '7k': 'bg-purple-500/10 text-purple-400',
};

window.TourneyMethod = window.TourneyMethod || {};
window.TourneyMethod.gamemodes = {
    label(mode) {
        return labels[mode] || mode || '';
    },
    icon(mode) {
        return icons[mode] || null;
    },
    badgeClasses(mode) {
        return badgeClasses[mode] || 'bg-slate-700/50 text-slate-300';
    },
};
