<?php

use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses(RefreshDatabase::class);

test('tournament card shows format badges and progression chip', function () {
    $tournament = Tournament::factory()->create([
        'team_formation_style' => 'suiji',
        'format_tags' => ['suiji', 'battle_royale'],
        'format_structure' => [
            'stages' => [
                ['type' => 'battle_royale', 'lobby_count' => 4, 'players_per_lobby' => 8, 'advance_per_lobby' => 4],
            ],
        ],
    ]);

    $html = Blade::render('<x-tournament-card :tournament="$tournament" />', [
        'tournament' => $tournament,
    ]);

    expect($html)->toContain('Suiji');
    expect($html)->toContain('Battle Royale');
    expect($html)->toContain('3 Rounds');
    expect($html)->not->toContain('tournament-card-format-badge');
});

test('tournament card only shows last progression stage', function () {
    $tournament = Tournament::factory()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 64],
                ['type' => 'group_stage', 'advance_count' => 32],
                ['type' => 'bracket', 'start_round_size' => 32, 'elimination_type' => 'double_elimination'],
            ],
        ],
    ]);

    $html = Blade::render('<x-tournament-card :tournament="$tournament" />', [
        'tournament' => $tournament,
    ]);

    expect($html)->toContain('Ro32 Double Elimination');
    expect($html)->not->toContain('Qualifier (Top 64)');
    expect($html)->not->toContain('Group Stage (Top 32)');
});

test('tournament card can show swiss round as last progression stage', function () {
    $tournament = Tournament::factory()->create([
        'format_structure' => [
            'stages' => [
                ['type' => 'qualifier', 'advance_count' => 64],
                ['type' => 'swiss_round', 'round_count' => 5, 'advance_count' => 16],
            ],
        ],
    ]);

    $html = Blade::render('<x-tournament-card :tournament="$tournament" />', [
        'tournament' => $tournament,
    ]);

    expect($html)->toContain('Swiss 5R Top 16');
    expect($html)->not->toContain('Qualifier (Top 64)');
});

test('tournament card does not show standard formation text', function () {
    $tournament = Tournament::factory()->create([
        'team_formation_style' => 'standard',
    ]);

    $html = Blade::render('<x-tournament-card :tournament="$tournament" />', [
        'tournament' => $tournament,
    ]);

    expect($html)->not->toContain('Standard');
});
