<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Services\SearchCommandParser;
use Tests\TestCase;

final class SearchCommandParserTest extends TestCase
{
    private SearchCommandParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new SearchCommandParser;
    }

    public function test_parses_commands_and_preserves_keyword(): void
    {
        $criteria = $this->parser->parse('winter cup rank:1000 sr:5.5 country:cn staff:Garalulu@organizer podium:Garalulu@1st');

        $this->assertSame('winter cup', $criteria->keyword);
        $this->assertSame(1000, $criteria->rank);
        $this->assertSame(5.5, $criteria->starRating);
        $this->assertSame(['CN'], $criteria->countries);
        $this->assertSame('Garalulu', $criteria->staffUsername);
        $this->assertSame('organizer', $criteria->staffRole);
        $this->assertSame('Garalulu', $criteria->podiumUsername);
        $this->assertSame(1, $criteria->podiumPlacement);
    }

    public function test_parses_quoted_usernames_and_multiple_countries(): void
    {
        $criteria = $this->parser->parse('staff:"Some User"@mapper countries:CN,TW,hk podium:"Other User"@2nd');

        $this->assertSame('', $criteria->keyword);
        $this->assertSame(['CN', 'TW', 'HK'], $criteria->countries);
        $this->assertSame('Some User', $criteria->staffUsername);
        $this->assertSame('mapper', $criteria->staffRole);
        $this->assertSame('Other User', $criteria->podiumUsername);
        $this->assertSame(2, $criteria->podiumPlacement);
    }

    public function test_leaves_localized_or_invalid_commands_as_keyword(): void
    {
        $criteria = $this->parser->parse('역할:Garalulu@플레이테스터 staff:Garalulu@플레이테스터 podium:Garalulu@winner');

        $this->assertSame('역할:Garalulu@플레이테스터 staff:Garalulu@플레이테스터 podium:Garalulu@winner', $criteria->keyword);
        $this->assertFalse($criteria->hasCommands());
    }

    public function test_accepts_numeric_podium_aliases(): void
    {
        $criteria = $this->parser->parse('podium:Garalulu@3');

        $this->assertSame('Garalulu', $criteria->podiumUsername);
        $this->assertSame(3, $criteria->podiumPlacement);
    }

    public function test_parses_podium_command_without_placement(): void
    {
        $criteria = $this->parser->parse('podium:Garalulu');

        $this->assertSame('', $criteria->keyword);
        $this->assertSame('Garalulu', $criteria->podiumUsername);
        $this->assertNull($criteria->podiumPlacement);
        $this->assertTrue($criteria->hasCommands());
    }

    public function test_parses_staff_command_without_role(): void
    {
        $criteria = $this->parser->parse('staff:Garalulu');

        $this->assertSame('', $criteria->keyword);
        $this->assertSame('Garalulu', $criteria->staffUsername);
        $this->assertNull($criteria->staffRole);
        $this->assertTrue($criteria->hasCommands());
    }

    public function test_parses_allowed_format_commands(): void
    {
        $criteria = $this->parser->parse('format:world-cup format:battle-royale winter cup');

        $this->assertSame('winter cup', $criteria->keyword);
        $this->assertSame('battle_royale', $criteria->formatTag);
        $this->assertTrue($criteria->hasCommands());
    }

    public function test_leaves_non_unique_format_commands_as_keyword(): void
    {
        $criteria = $this->parser->parse('format:qualifier format:swiss format:double-elim');

        $this->assertSame('format:qualifier format:swiss format:double-elim', $criteria->keyword);
        $this->assertNull($criteria->formatTag);
        $this->assertFalse($criteria->hasCommands());
    }
}
