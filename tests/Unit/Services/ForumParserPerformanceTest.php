<?php

use App\Services\ForumParser;

// ============================================================================
// Phase 1: Quick Wins - Performance Tests
// ============================================================================

beforeEach(function () {
    $this->parser = app(ForumParser::class);
});

// Test 1.1: Early Exit for Empty Content
test('parseStaffFromBBcode returns empty for short content (<50 chars)', function () {
    $bbcode = 'Short'; // 5 characters

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('parseStaffFromBBcode early exit avoids regex processing for invalid content', function () {
    // This test validates that early exits prevent expensive regex operations
    // by measuring that processing time is minimal for invalid content

    $iterations = 1000;
    $invalidContent = str_repeat('a', 60); // 60 chars, no role headers, no user links

    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $this->parser->parseStaffFromBBcode($invalidContent);
    }
    $duration = (microtime(true) - $start) * 1000;

    $avgTime = $duration / $iterations;

    // With early exits, should process 1000 invalid posts in <100ms total
    // Average <0.1ms per invalid post
    expect($avgTime)->toBeLessThan(0.1);
})->group('performance');

test('parseStaffFromBBcode returns empty for empty string', function () {
    $bbcode = '';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('parseStaffFromBBcode returns empty for whitespace only', function () {
    $bbcode = "   \n\n\t   ";

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('parseStaffFromBBcode processes valid content (>50 chars)', function () {
    $bbcode = '[b][color=#cf68dd]Admin:[/color][/b] [url=https://osu.ppy.sh/users/123]User[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->not->toBeEmpty();
    expect($result)->toHaveCount(1);
});

// Test 1.2: Early Exit for No Role Headers
test('parseStaffFromBBcode returns empty when no role headers present', function () {
    $bbcode = 'Just some text without bold role headers. This is long enough to pass length check but has no structure. More text here to make it longer than fifty characters total.';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('parseStaffFromBBcode returns empty when only closing bold tag', function () {
    $bbcode = 'Some text [/b] but no opening tag. This is long enough text to pass the minimum length check and exceed fifty characters.';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('parseStaffFromBBcode processes content with role headers', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/123]User[/url] More text here to make it long enough and exceed fifty characters minimum.';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->not->toBeEmpty();
});

// Test 1.3: Early Exit for No User Links
test('parseStaffFromBBcode returns empty when no user links present', function () {
    // Updated: Now supports plain text format, so this test needs to NOT match plain text pattern
    // Use text without role:username pattern to ensure early exit
    $bbcode = 'This is just random text without any role headers or user links. It is long enough to pass the minimum length check but has no structured data to extract from the forum post.';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('parseStaffFromBBcode processes content with user links', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/123]User[/url] More text to make it long enough and exceed the minimum fifty character requirement.';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->not->toBeEmpty();
    expect($result[0]['osu_id'])->toBe(123);
});

// Test 1.4: Performance Benchmark - Early Exit Impact
test('parseStaffFromBBcode early exit performance: short content', function () {
    $iterations = 100;
    $bbcode = 'Short'; // < 50 chars

    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $this->parser->parseStaffFromBBcode($bbcode);
    }
    $duration = (microtime(true) - $start) * 1000; // Convert to ms

    $avgTime = $duration / $iterations;

    expect($avgTime)->toBeLessThan(0.5); // <0.5ms average per iteration
})->group('performance');

test('parseStaffFromBBcode early exit performance: no role headers', function () {
    $iterations = 100;
    $bbcode = 'Just some text without bold role headers. This is long enough to pass length check but has no structure. And we add more text to ensure it exceeds fifty characters.';

    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $this->parser->parseStaffFromBBcode($bbcode);
    }
    $duration = (microtime(true) - $start) * 1000;

    $avgTime = $duration / $iterations;

    expect($avgTime)->toBeLessThan(1.0); // <1ms average per iteration
})->group('performance');

// Test 1.5: Multi-Format Fallback System
test('parseStaffFromBBcode handles mixed BBcode formats with fallback', function () {
    // Updated: Multi-format system uses first matching extractor
    // This test uses nested bold+color format which matches first
    $bbcode = '[b][color=#cf68dd]Admin:[/color][/b] [url=https://osu.ppy.sh/users/123]User1[/url]
    [b][color=#EEAA75]Referee:[/color][/b] [url=https://osu.ppy.sh/users/789]User3[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2);
    expect($result[0])->toMatchArray([
        'osu_id' => 123,
        'username' => 'User1',
        'role' => 'organizer',
    ]);
    expect($result[1])->toMatchArray([
        'osu_id' => 789,
        'username' => 'User3',
        'role' => 'referee',
    ]);
});

test('parseStaffFromBBcode backward compatible with color-only format', function () {
    $bbcode = '[b][color=#cf68dd]Admin:[/color][/b] [url=https://osu.ppy.sh/users/123]User1[/url]
    [b][color=#cf68dd]Mapper:[/color][/b] [url=https://osu.ppy.sh/users/456]User2[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2);
});

test('parseStaffFromBBcode backward compatible with no-color format', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/123]User1[/url]
    [b]Mapper:[/b] [url=https://osu.ppy.sh/users/456]User2[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2);
});

// Test 1.6: Multi-role support (no deduplication by osu_id)
test('parseStaffFromBBcode preserves all roles for same user', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/123]SameUser[/url]
    [b]Mapper:[/b] [url=https://osu.ppy.sh/users/123]SameUser[/url]
    [b]Referee:[/b] [url=https://osu.ppy.sh/users/123]SameUser[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    // Multi-role support: SameUser appears with all 3 roles
    expect($result)->toHaveCount(3);
    expect($result[0]['osu_id'])->toBe(123);
    expect($result[0]['username'])->toBe('SameUser');
    expect($result[0]['role'])->toBe('organizer');
    expect($result[1]['osu_id'])->toBe(123);
    expect($result[1]['role'])->toBe('mapper');
    expect($result[2]['osu_id'])->toBe(123);
    expect($result[2]['role'])->toBe('referee');
});

test('parseStaffFromBBcode handles multiple unique entries', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/1]User1[/url]
    [b]Mapper:[/b] [url=https://osu.ppy.sh/users/2]User2[/url]
    [b]Referee:[/b] [url=https://osu.ppy.sh/users/3]User3[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0]['osu_id'])->toBe(1);
    expect($result[1]['osu_id'])->toBe(2);
    expect($result[2]['osu_id'])->toBe(3);
});

test('parseStaffFromBBcode handles mixed duplicates and uniques', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/1]User1[/url]
    [b]Mapper:[/b] [url=https://osu.ppy.sh/users/2]User2[/url]
    [b]Referee:[/b] [url=https://osu.ppy.sh/users/1]User1[/url]
    [b]Playtester:[/b] [url=https://osu.ppy.sh/users/3]User3[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    // Multi-role support: User1 appears with 2 roles (Admin, Referee)
    // Total: User1 (2 roles) + User2 + User3 = 4 entries
    expect($result)->toHaveCount(4);
});

// Test 1.7: Performance Benchmark - Phase 1 Complete
test('parseStaffFromBBcode Phase 1 performance: 50 staff members', function () {
    $bbcode = generateLargeStaffPost(50);

    $start = microtime(true);
    $result = $this->parser->parseStaffFromBBcode($bbcode);
    $duration = (microtime(true) - $start) * 1000;

    expect($result)->toHaveCount(50);
    expect($duration)->toBeLessThan(50); // <50ms target
})->group('performance');

test('parseStaffFromBBcode Phase 1 performance: 100 staff members', function () {
    $bbcode = generateLargeStaffPost(100);

    $start = microtime(true);
    $result = $this->parser->parseStaffFromBBcode($bbcode);
    $duration = (microtime(true) - $start) * 1000;

    expect($result)->toHaveCount(100);
    expect($duration)->toBeLessThan(100); // <100ms target
})->group('performance');

// ============================================================================
// Phase 2: Advanced Optimizations - Performance Tests
// ============================================================================

test('parseStaffFromBBcode multi-format fallback system', function () {
    // Updated: Multi-format system uses first matching extractor
    // This test uses nested bold+color format which matches first
    $bbcode = '[b][color=#cf68dd]Admin:[/color][/b] [url=https://osu.ppy.sh/users/123]User1[/url] | [url=https://osu.ppy.sh/users/456]User2[/url]
    [b][color=#cf68dd]Mapper:[/color][/b] [url=https://osu.ppy.sh/users/789]User3[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0])->toMatchArray([
        'osu_id' => 123,
        'username' => 'User1',
        'role' => 'organizer',
    ]);
    expect($result[1])->toMatchArray([
        'osu_id' => 456,
        'username' => 'User2',
        'role' => 'organizer',
    ]);
    expect($result[2])->toMatchArray([
        'osu_id' => 789,
        'username' => 'User3',
        'role' => 'mapper',
    ]);
});

test('parseStaffFromBBcode handles empty staff sections', function () {
    $bbcode = '[b][color=#cf68dd]Admin:[/color][/b] [url=https://osu.ppy.sh/users/123]User1[/url]
    [b]Mapper:[/b]
    [b]Referee:[/b] [url=https://osu.ppy.sh/users/456]User2[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2); // Admin and Referee, Mapper has no staff
});

test('parseStaffFromBBcode uses precompiled patterns', function () {
    // This test ensures patterns are defined as class constants
    $reflection = new ReflectionClass(ForumParser::class);

    // Check for pattern constants (should exist after Phase 2)
    $constants = $reflection->getConstants();

    // Verify at least one pattern constant exists
    $patternKeys = array_filter(array_keys($constants), fn ($key) => str_contains($key, 'PATTERN'));

    expect($patternKeys)->not->toBeEmpty();
});

test('parseStaffFromBBcode Phase 2 performance: 50 staff < 30ms', function () {
    $bbcode = generateLargeStaffPost(50);

    $start = microtime(true);
    $result = $this->parser->parseStaffFromBBcode($bbcode);
    $duration = (microtime(true) - $start) * 1000;

    expect($result)->toHaveCount(50);
    expect($duration)->toBeLessThan(30); // <30ms target (70% improvement)
})->group('performance');

test('parseStaffFromBBcode Phase 2 performance: 100 staff < 60ms', function () {
    $bbcode = generateLargeStaffPost(100);

    $start = microtime(true);
    $result = $this->parser->parseStaffFromBBcode($bbcode);
    $duration = (microtime(true) - $start) * 1000;

    expect($result)->toHaveCount(100);
    expect($duration)->toBeLessThan(60); // <60ms target (75% improvement)
})->group('performance');

test('parseStaffFromBBcode Phase 2 performance: 500 staff < 300ms', function () {
    $bbcode = generateLargeStaffPost(500);

    $start = microtime(true);
    $result = $this->parser->parseStaffFromBBcode($bbcode);
    $duration = (microtime(true) - $start) * 1000;

    expect($result)->toHaveCount(500);
    expect($duration)->toBeLessThan(300); // <300ms target (80% improvement)
})->group('performance');

test('parseStaffFromBBcode performance benchmark: consistent timing', function () {
    $iterations = 100;
    $bbcode = generateLargeStaffPost(50);

    $times = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $this->parser->parseStaffFromBBcode($bbcode);
        $times[] = (microtime(true) - $start) * 1000;
    }

    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);
    $minTime = min($times);

    echo "\nPerformance Metrics:\n";
    echo "Average: {$avgTime}ms\n";
    echo "Min: {$minTime}ms\n";
    echo "Max: {$maxTime}ms\n";

    expect($avgTime)->toBeLessThan(30); // Average < 30ms
    expect($maxTime)->toBeLessThan(50); // Max < 50ms
})->group('performance');

// ============================================================================
// Phase 4: Edge Cases & Real-World Scenarios - Performance Tests
// ============================================================================

// Test 4.1: Unicode and Multilingual Content
test('parseStaffFromBBcode handles unicode usernames', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/123]ユーザー[/url]
    [b]Mapper:[/b] [url=https://osu.ppy.sh/users/456]한글이름[/url]
    [b]Referee:[/b] [url=https://osu.ppy.sh/users/789]اسم[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0]['username'])->toBe('ユーザー');
    expect($result[1]['username'])->toBe('한글이름');
    expect($result[2]['username'])->toBe('اسم');
});

test('parseStaffFromBBcode handles emoji in role names', function () {
    $bbcode = '[b]🎮 Mapper:[/b] [url=https://osu.ppy.sh/users/123]User1[/url]
    [b]🎨 GFX:[/b] [url=https://osu.ppy.sh/users/456]User2[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2);
    expect($result[0]['role'])->toBe('mapper');
    expect($result[1]['role'])->toBe('gfx');
});

test('parseStaffFromBBcode handles special characters in usernames', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/123]User_123[/url]
    [b]Mapper:[/b] [url=https://osu.ppy.sh/users/456]User-456[/url]
    [b]Referee:[/b] [url=https://osu.ppy.sh/users/789]User.Name[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0]['username'])->toBe('User_123');
    expect($result[1]['username'])->toBe('User-456');
    expect($result[2]['username'])->toBe('User.Name');
});

// Test 4.2: Malformed BBcode Handling
test('parseStaffFromBBcode handles unclosed BBcode tags', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/123]User1[/url]
    [b]Mapper: [url=https://osu.ppy.sh/users/456]User2[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2);
    expect($result[0]['osu_id'])->toBe(123);
    expect($result[1]['osu_id'])->toBe(456);
});

test('parseStaffFromBBcode cleans nested BBcode in usernames', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/123][color=red]User1[/color][/url]
    [b]Mapper:[/b] [url=https://osu.ppy.sh/users/456][b]User2[/b][/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2);
    expect($result[0]['username'])->toBe('User1');
    expect($result[1]['username'])->toBe('User2');
});

test('parseStaffFromBBcode handles extra whitespace and newlines', function () {
    $bbcode = '[b]Admin:[/b]

    [url=https://osu.ppy.sh/users/123]User1[/url]


    [b]Mapper:[/b]
    [url=https://osu.ppy.sh/users/456]User2[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2);
});

// Test 4.3: Case Sensitivity and Variations
test('parseStaffFromBBcode handles case variations in role names', function () {
    $bbcode = '[b]ADMIN:[/b] [url=https://osu.ppy.sh/users/123]User1[/url]
    [b]mapper:[/b] [url=https://osu.ppy.sh/users/456]User2[/url]
    [b]ReFeReE:[/b] [url=https://osu.ppy.sh/users/789]User3[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
    expect($result[0]['role'])->toBe('organizer');
    expect($result[1]['role'])->toBe('mapper');
    expect($result[2]['role'])->toBe('referee');
});

test('parseStaffFromBBcode handles color hex variations', function () {
    $bbcode = '[b][color=#cf68dd]Admin:[/color][/b] [url=https://osu.ppy.sh/users/123]User1[/url]
    [b][color=#EEAA75]Mapper:[/color][/b] [url=https://osu.ppy.sh/users/456]User2[/url]
    [b][color=#ff0000]Referee:[/color][/b] [url=https://osu.ppy.sh/users/789]User3[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(3);
});

// Test 4.4: URL Variations
test('parseStaffFromBBcode handles http vs https URLs', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/123]User1[/url]
    [b]Mapper:[/b] [url=http://osu.ppy.sh/users/456]User2[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(2);
    expect($result[0]['osu_id'])->toBe(123);
    expect($result[1]['osu_id'])->toBe(456);
});

test('parseStaffFromBBcode handles URLs with query parameters', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/123?param=value]User1[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(1);
    expect($result[0]['osu_id'])->toBe(123);
});

test('parseStaffFromBBcode handles URLs with fragments', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/123#section]User1[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(1);
    expect($result[0]['osu_id'])->toBe(123);
});

// Test 4.5: Large Dataset Performance
test('parseStaffFromBBcode handles 1000 staff members efficiently', function () {
    $bbcode = generateLargeStaffPost(1000);

    $start = microtime(true);
    $result = $this->parser->parseStaffFromBBcode($bbcode);
    $duration = (microtime(true) - $start) * 1000;

    expect($result)->toHaveCount(1000);
    expect($duration)->toBeLessThan(600); // <600ms for 1000 staff
})->group('performance');

test('parseStaffFromBBcode memory usage for large datasets', function () {
    $bbcode = generateLargeStaffPost(500);

    $memoryBefore = memory_get_usage();
    $result = $this->parser->parseStaffFromBBcode($bbcode);
    $memoryAfter = memory_get_usage();

    $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

    echo "\nMemory usage: {$memoryUsed}MB\n";

    expect($result)->toHaveCount(500);
    expect($memoryUsed)->toBeLessThan(10); // <10MB for 500 staff
})->group('performance');

// Test 4.6: Real-World Forum Post Samples
test('parseStaffFromBBcode handles real-world tournament post format 1', function () {
    // Based on actual osu! tournament posts
    $bbcode = '[centre][b][color=#cf68dd]Tournament Staff[/color][/b][/centre]

[b][color=#cf68dd]Hosts:[/color][/b] [url=https://osu.ppy.sh/users/1234567]Host1[/url], [url=https://osu.ppy.sh/users/2345678]Host2[/url]
[b][color=#cf68dd]Mappoolers:[/color][/b] [url=https://osu.ppy.sh/users/3456789]Mapper1[/url], [url=https://osu.ppy.sh/users/4567890]Mapper2[/url], [url=https://osu.ppy.sh/users/5678901]Mapper3[/url]
[b][color=#cf68dd]Referees:[/color][/b] [url=https://osu.ppy.sh/users/6789012]Ref1[/url], [url=https://osu.ppy.sh/users/7890123]Ref2[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(7);
    expect($result[0]['role'])->toBe('organizer');
    expect($result[2]['role'])->toBe('mappooler');
    expect($result[5]['role'])->toBe('referee');
});

test('parseStaffFromBBcode handles real-world tournament post format 2', function () {
    // Alternative format with no color tags
    $bbcode = '[b]Organizers:[/b]
[url=https://osu.ppy.sh/users/1]User1[/url] [url=https://osu.ppy.sh/users/2]User2[/url]

[b]Playtesters:[/b]
[url=https://osu.ppy.sh/users/3]User3[/url]
[url=https://osu.ppy.sh/users/4]User4[/url]

[b]Streamers:[/b]
[url=https://osu.ppy.sh/users/5]User5[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(5);
    expect($result[0]['role'])->toBe('organizer');
    expect($result[2]['role'])->toBe('playtester');
    expect($result[4]['role'])->toBe('streamer');
});

test('parseStaffFromBBcode handles mixed delimiter formats', function () {
    // Comma-separated, pipe-separated, and newline-separated
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/1]User1[/url], [url=https://osu.ppy.sh/users/2]User2[/url]
    [b]Mapper:[/b] [url=https://osu.ppy.sh/users/3]User3[/url] | [url=https://osu.ppy.sh/users/4]User4[/url]
    [b]Referee:[/b] [url=https://osu.ppy.sh/users/5]User5[/url]
    [url=https://osu.ppy.sh/users/6]User6[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(6);
});

// Test 4.7: Boundary Conditions
test('parseStaffFromBBcode handles exactly 50 character content', function () {
    $bbcode = str_repeat('a', 50); // Exactly 50 chars, no structure

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toBeEmpty();
});

test('parseStaffFromBBcode handles 51 character valid content', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/123]User[/url]';
    // This is 55 chars, valid content

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->not->toBeEmpty();
});

test('parseStaffFromBBcode handles maximum valid osu! user ID', function () {
    // Maximum osu! user ID (as of 2024)
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/999999999]User1[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(1);
    expect($result[0]['osu_id'])->toBe(999999999);
});

test('parseStaffFromBBcode handles minimum valid osu! user ID', function () {
    $bbcode = '[b]Admin:[/b] [url=https://osu.ppy.sh/users/1]User1[/url]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(1);
    expect($result[0]['osu_id'])->toBe(1);
});

// Test 4.8: Empty and Null Handling
test('parseStaffFromBBcode handles null input gracefully', function () {
    expect(fn () => $this->parser->parseStaffFromBBcode(null))
        ->toThrow(TypeError::class);
});

test('parseStaffFromBBcode handles role with no staff members', function () {
    $bbcode = '[b]Admin:[/b]
    [b]Mapper:[/b] [url=https://osu.ppy.sh/users/456]User2[/url]
    [b]Referee:[/b]';

    $result = $this->parser->parseStaffFromBBcode($bbcode);

    expect($result)->toHaveCount(1);
    expect($result[0]['role'])->toBe('mapper');
});

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Generate large staff post for performance testing
 */
function generateLargeStaffPost(int $count): string
{
    $roles = [
        'Admin', 'Mapper', 'Mappooler', 'Referee',
        'Playtester', 'GFX', 'Sheeter', 'Streamer',
        'Commentator', 'Custom Mapper', 'Map Selector',
    ];

    $bbcode = '';

    for ($i = 0; $i < $count; $i++) {
        $role = $roles[$i % count($roles)];
        $userId = 1000 + $i;
        $bbcode .= "[b][color=#cf68dd]{$role}:[/color][/b] ";
        $bbcode .= "[url=https://osu.ppy.sh/users/{$userId}]User{$i}[/url]\n";
    }

    return $bbcode;
}
