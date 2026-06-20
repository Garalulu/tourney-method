<?php

namespace App\Services;

use App\Models\TournamentParticipationRecord;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\YearRecapCache;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Geometry\Ellipse;
use Intervention\Image\Geometry\Line;
use Intervention\Image\Geometry\Point;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\Font;

class YearRecapService
{
    protected ImageManager $imageManager;

    protected ?string $fontPath;

    protected ?string $fontBoldPath;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver);

        // Use system fonts as fallback (can be overridden with custom fonts)
        $this->fontPath = $this->getFontPath('Inter-Regular.ttf');
        $this->fontBoldPath = $this->getFontPath('Outfit-Bold.ttf');
    }

    /**
     * Generate or retrieve cached year recap image for a user.
     *
     * @return array{image_url: string, generated_at: string, stats: array<string, mixed>}
     *
     * @throws \Exception
     */
    public function getOrCreateRecap(User $user, int $year): array
    {
        $cache = YearRecapCache::query()
            ->forUserAndYear($user->id, $year)
            ->first();

        // Check if cache exists and is still valid (24 hours)
        if ($cache && $cache->generated_at->gt(now()->subHours(24))) {
            return [
                'image_url' => Storage::url($cache->image_path),
                'generated_at' => $cache->generated_at->toIso8601String(),
                'stats' => $cache->stats_json ?? [],
            ];
        }

        // Generate new recap
        return $this->generateRecap($user, $year);
    }

    /**
     * Generate a new year recap image.
     *
     * @return array{image_url: string, generated_at: string, stats: array<string, mixed>}
     *
     * @throws \Exception
     */
    protected function generateRecap(User $user, int $year): array
    {
        $stats = $this->calculateYearStats($user, $year);

        if ($stats['total_matches'] === 0) {
            throw new \Exception("No match data found for year {$year}");
        }

        // Create 1200x630 canvas
        $image = $this->imageManager->create(1200, 630);

        // Fill background with dark gradient
        $image->fill('#1a1a2e');

        // Add gradient overlay
        $this->addGradientOverlay($image);

        // Add user avatar
        $this->addAvatar($image, $user->avatar_url, $user->username);

        // Add title
        $this->addTitle($image, $user->username, $year);

        // Add stats section
        $this->addStatsSection($image, $stats);

        // Add teammates and opponents
        $this->addPlayerSections($image, $stats);

        // Add footer
        $this->addFooter($image);

        // Store image
        $path = "recaps/{$user->id}/{$year}.png";
        Storage::put($path, (string) $image->toPng());

        // Update or create cache record
        YearRecapCache::updateOrCreateForUser(
            [
                'user_id' => $user->id,
                'year' => $year,
            ],
            [
                'image_path' => $path,
                'stats_json' => $stats,
                'generated_at' => now(),
            ]
        );

        return [
            'image_url' => Storage::url($path),
            'generated_at' => now()->toIso8601String(),
            'stats' => $stats,
        ];
    }

    /**
     * Calculate statistics for a specific year.
     *
     * @return array<string, mixed>
     */
    public function calculateYearStats(User $user, int $year): array
    {
        $yearStart = now()->setDate($year, 1, 1)->startOfDay();
        $yearEnd = now()->setDate($year, 12, 31)->endOfDay();

        $records = TournamentParticipationRecord::query()
            ->where('user_id', $user->id)
            ->where('review_status', TournamentParticipationRecord::REVIEW_APPROVED)
            ->whereHas('tournament', fn ($query) => $query->whereBetween('tournament_end', [$yearStart, $yearEnd]))
            ->with(['participationMatches', 'teammates:id,username'])
            ->get();

        $totalMatches = 0;
        $totalGames = 0;
        $gamesWon = 0;
        foreach ($records as $record) {
            foreach ($record->participationMatches as $match) {
                $totalMatches++;
                if ($match->score_for === null || $match->score_against === null) {
                    continue;
                }

                $totalGames++;
                if ($match->score_for > $match->score_against) {
                    $gamesWon++;
                }
            }
        }
        $winRate = $totalGames > 0
            ? round(($gamesWon / $totalGames) * 100, 1)
            : 0.0;

        // Get badges earned during the year
        $badgesEarned = UserBadge::query()
            ->where('user_id', $user->id)
            ->whereBetween('awarded_at', [$yearStart, $yearEnd])
            ->count();

        return [
            'year' => $year,
            'total_matches' => $totalMatches,
            'total_games' => $totalGames,
            'games_won' => $gamesWon,
            'win_rate' => $winRate,
            'badges_earned' => $badgesEarned,
            'frequent_teammates' => $this->getYearlyTeammates($records),
            'frequent_opponents' => [],
        ];
    }

    /**
     * Get frequent teammates for a specific year.
     *
     * @param  EloquentCollection<int, TournamentParticipationRecord>  $records
     * @return array<int, array{username: string, match_count: int}>
     */
    protected function getYearlyTeammates(EloquentCollection $records): array
    {
        $teammateCounts = [];

        foreach ($records as $record) {
            foreach ($record->teammates as $teammate) {
                $teammateCounts[$teammate->id] ??= [
                    'username' => $teammate->username,
                    'match_count' => 0,
                ];
                $teammateCounts[$teammate->id]['match_count']++;
            }
        }

        return collect($teammateCounts)
            ->filter(fn (array $teammate) => $teammate['match_count'] >= 3)
            ->sortByDesc('match_count')
            ->values()
            ->take(3)
            ->toArray();
    }

    /**
     * Add gradient overlay to image.
     */
    protected function addGradientOverlay(ImageInterface $image): void
    {
        // Simplified gradient effect using solid colors
        // Create a subtle gradient from dark to slightly lighter pink
        for ($i = 0; $i < 630; $i += 10) {
            $intensity = (int) (26 + ($i / 630) * 15);
            $colorHex = sprintf('#%02x%02x%02x', $intensity, 20, 40);

            $image->drawRectangle(0, $i, fn ($rect) => $rect
                ->size(1200, 10)
                ->background($colorHex)
            );
        }
    }

    /**
     * Add user avatar to image.
     */
    protected function addAvatar(ImageInterface $image, ?string $avatarUrl, string $username): void
    {
        $avatarSize = 120;
        $x = 50;
        $y = 50;

        // Draw avatar background circle using ellipse
        $image->drawEllipse(
            (int) $x + $avatarSize / 2,
            (int) $y + $avatarSize / 2,
            fn ($ellipse) => $ellipse
                ->size($avatarSize, $avatarSize)
        )->fill('rgba(236, 72, 153, 0.3)');

        if ($avatarUrl) {
            try {
                $avatarData = @file_get_contents($avatarUrl);
                if ($avatarData !== false) {
                    $avatarImage = $this->imageManager->read($avatarData);
                    $avatarImage->resize($avatarSize, $avatarSize);

                    // For Intervention Image v3, place directly without mask
                    // The circular background provides visual framing
                    $image->place($avatarImage, 'top-left', $x, $y);

                    return;
                }
            } catch (\Exception $e) {
                // Fall through to fallback
            }
        }

        // Fallback: draw user initial
        $initial = strtoupper(substr($username, 0, 1));
        $font = new Font($this->fontBoldPath);
        $font->setSize(48)->setColor('#ec4899')->setAlignment('center')->setValignment('middle');

        $image->text(
            $initial,
            (int) $x + $avatarSize / 2,
            (int) $y + $avatarSize / 2 + 20,
            $font
        );
    }

    /**
     * Add title text.
     */
    protected function addTitle(ImageInterface $image, string $username, int $year): void
    {
        $x = 200;
        $y = 80;

        $font = new Font($this->fontBoldPath);
        $font->setSize(42)->setColor('#ffffff')->setAlignment('left')->setValignment('top');

        $image->text("{$username}'s {$year} Recap", $x, $y, $font);
    }

    /**
     * Add stats section.
     *
     * @param  array{total_matches: int, total_games: int, win_rate: float, badges_earned: int, frequent_teammates: array<int, array{username: string, match_count: int}>, frequent_opponents: array<int, array{username: string, match_count: int}>}  $stats
     */
    protected function addStatsSection(ImageInterface $image, array $stats): void
    {
        $startY = 180;
        $y = $startY;
        $col1X = 200;
        $col2X = 600;
        $spacing = 80;

        // Tournament stats
        $this->addStatItem($image, $col1X, $y, '🏆', 'Tournaments', $stats['total_matches']);
        $y += $spacing;
        $this->addStatItem($image, $col2X, $y, '📊', 'Matches', $stats['total_games']);
        $y += $spacing;
        $this->addStatItem($image, $col1X, $y, '🎯', 'Win Rate', (string) $stats['win_rate'].'%');
        $y += $spacing;
        $this->addStatItem($image, $col2X, $y, '🏅', 'Badges', $stats['badges_earned']);
    }

    /**
     * Add a single stat item.
     *
     * @param  int|string  $value
     */
    protected function addStatItem(ImageInterface $image, int $x, int $y, string $emoji, string $label, $value): void
    {
        $fontNormal = new Font($this->fontPath);
        $fontNormal->setSize(24)->setColor('#94a3b8')->setAlignment('left')->setValignment('top');

        $fontBold = new Font($this->fontBoldPath);
        $fontBold->setSize(32)->setColor('#ffffff')->setAlignment('left')->setValignment('top');

        // Emoji and label
        $image->text("{$emoji} {$label}", $x, $y, $fontNormal);

        // Value
        $image->text((string) $value, $x, $y + 30, $fontBold);
    }

    /**
     * Add player sections (teammates and opponents).
     *
     * @param  array{frequent_teammates: array<int, array{username: string, match_count: int}>, frequent_opponents: array<int, array{username: string, match_count: int}>}  $stats
     */
    protected function addPlayerSections(ImageInterface $image, array $stats): void
    {
        $y = 480;
        $leftX = 100;
        $rightX = 700;

        $fontBold = new Font($this->fontBoldPath);
        $fontBold->setSize(20)->setColor('#ec4899')->setAlignment('left')->setValignment('top');

        $fontNormal = new Font($this->fontPath);
        $fontNormal->setSize(16)->setColor('#cbd5e1')->setAlignment('left')->setValignment('top');

        $fontNoData = new Font($this->fontPath);
        $fontNoData->setSize(16)->setColor('#64748b')->setAlignment('left')->setValignment('top');

        // Divider line
        $line = new Line(new Point(100, 450), new Point(1100, 450));
        $image->drawLine($line)->fill('rgba(255, 255, 255, 0.2)');

        // Teammates
        $image->text('Top Teammates', $leftX, $y, $fontBold);

        $y += 35;
        foreach ($stats['frequent_teammates'] as $i => $teammate) {
            $image->text("• {$teammate['username']} ({$teammate['match_count']})", $leftX, $y + ($i * 28), $fontNormal);
        }

        if (empty($stats['frequent_teammates'])) {
            $image->text('No data', $leftX, $y, $fontNoData);
        }

        // Opponents
        $image->text('Top Opponents', $rightX, $y, $fontBold);

        $y += 35;
        foreach ($stats['frequent_opponents'] as $i => $opponent) {
            $image->text("• {$opponent['username']} ({$opponent['match_count']})", $rightX, $y + ($i * 28), $fontNormal);
        }

        if (empty($stats['frequent_opponents'])) {
            $image->text('No data', $rightX, $y, $fontNoData);
        }
    }

    /**
     * Add footer.
     */
    protected function addFooter(ImageInterface $image): void
    {
        $y = 600;

        $fontBold = new Font($this->fontBoldPath);
        $fontBold->setSize(18)->setColor('#94a3b8')->setAlignment('left')->setValignment('top');

        $fontNormal = new Font($this->fontPath);
        $fontNormal->setSize(16)->setColor('#64748b')->setAlignment('right')->setValignment('top');

        // Divider
        $line = new Line(new Point(100, 570), new Point(1100, 570));
        $image->drawLine($line)->fill('rgba(255, 255, 255, 0.2)');

        // Left: Tourney Method
        $image->text('Tourney Method', 100, $y, $fontBold);

        // Right: URL
        $image->text('tourneymethod.com', 1100, $y, $fontNormal);
    }

    /**
     * Get font path - falls back to built-in fonts if custom fonts not available.
     */
    protected function getFontPath(string $filename): ?string
    {
        $customPath = resource_path("fonts/{$filename}");
        if (file_exists($customPath)) {
            return $customPath;
        }

        // Use system fallback fonts
        // On Windows: arial.ttf
        // On Linux: /usr/share/fonts/truetype/dejavu/DejaVuSans.ttf
        // On macOS: /System/Library/Fonts/Helvetica.ttc

        if (PHP_OS_FAMILY === 'Windows' && file_exists('C:\\Windows\\Fonts\\arial.ttf')) {
            return 'C:\\Windows\\Fonts\\arial.ttf';
        }

        if (PHP_OS_FAMILY === 'Darwin' && file_exists('/System/Library/Fonts/Helvetica.ttc')) {
            return '/System/Library/Fonts/Helvetica.ttc';
        }

        if (file_exists('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf')) {
            return '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        }

        // Last resort - return null to use GD's default font
        return null;
    }

    /**
     * Check if user has data for a specific year.
     */
    public function hasDataForYear(User $user, int $year): bool
    {
        $yearStart = now()->setDate($year, 1, 1)->startOfDay();
        $yearEnd = now()->setDate($year, 12, 31)->endOfDay();

        return TournamentParticipationRecord::query()
            ->where('user_id', $user->id)
            ->where('review_status', TournamentParticipationRecord::REVIEW_APPROVED)
            ->whereHas('tournament', fn ($query) => $query->whereBetween('tournament_end', [$yearStart, $yearEnd]))
            ->whereHas('participationMatches')
            ->exists();
    }
}
