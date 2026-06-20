<?php

namespace App\Services;

class BbcodeParser
{
    /**
     * Convert BBcode to Markdown
     *
     * @param  string|null  $bbcode  The BBcode content to convert
     */
    public function toMarkdown(?string $bbcode): string
    {
        if ($bbcode === null || $bbcode === '') {
            return '';
        }

        $markdown = $bbcode;

        // Process in order of specificity (most specific first)

        // Handle [url=link][img]image[/img][/url] pattern first (before individual tags)
        $markdown = preg_replace('~\[url=([^\]]+)\]\[img\]([^\[]+)\[/img\]\[/url\]~i', '![image]($2)', $markdown);

        // Handle [img] tags (using greedy match for content)
        $markdown = preg_replace('~\[img\](.+?)\[/img\]~is', '![image]($1)', $markdown);

        // Handle [url=link]text[/url] format
        $markdown = preg_replace('~\[url=([^\]]+)\](.+?)\[/url\]~is', '[$2]($1)', $markdown);

        // Handle [url]link[/url] format
        $markdown = preg_replace('~\[url\](.+?)\[/url\]~is', '[$1]($1)', $markdown);

        // Text formatting
        $markdown = preg_replace('~\[b\](.+?)\[/b\]~is', '**$1**', $markdown);
        $markdown = preg_replace('~\[i\](.+?)\[/i\]~is', '*$1*', $markdown);
        $markdown = preg_replace('~\[u\](.+?)\[/u\]~is', '__$1__', $markdown);
        $markdown = preg_replace('~\[s\](.+?)\[/s\]~is', '~~$1~~', $markdown);

        // Block elements
        $markdown = preg_replace('~\[heading\](.+?)\[/heading\]~is', '### $1', $markdown);
        $markdown = preg_replace('~\[centre\](.+?)\[/centre\]~is', '$1', $markdown);
        $markdown = preg_replace('~\[center\](.+?)\[/center\]~is', '$1', $markdown);

        // Strip [notice], [color], [size] tags but keep content
        $markdown = preg_replace('~\[notice\](.+?)\[/notice\]~is', '$1', $markdown);
        $markdown = preg_replace('~\[color=[^\]]+\](.+?)\[/color\]~is', '$1', $markdown);
        $markdown = preg_replace('~\[size=[^\]]+\](.+?)\[/size\]~is', '$1', $markdown);
        $markdown = preg_replace('~\[size\](.+?)\[/size\]~is', '$1', $markdown);

        // Handle [list][*]items[/list] format - add newlines for list items
        $markdown = preg_replace('~\[list\]~i', '', $markdown);
        $markdown = preg_replace('~\[/list\]~i', '', $markdown);
        $markdown = preg_replace('~\[\*\]~i', "\n* ", $markdown);

        return $markdown;
    }

    /**
     * Extract all URLs from BBcode content
     *
     * @return array<int, string>
     */
    public function extractUrls(string $bbcode): array
    {
        if ($bbcode === '') {
            return [];
        }

        $urls = [];

        // Match [url=link]text[/url] format
        if (preg_match_all('~\[url=([^\]]+)\]~i', $bbcode, $matches)) {
            $urls = array_merge($urls, $matches[1]);
        }

        // Match [url]link[/url] format
        if (preg_match_all('~\[url\]([^\[]+)\[/url\]~i', $bbcode, $matches)) {
            $urls = array_merge($urls, $matches[1]);
        }

        // Match [img]link[/img] format
        if (preg_match_all('~\[img\]([^\[]+)\[/img\]~i', $bbcode, $matches)) {
            $urls = array_merge($urls, $matches[1]);
        }

        // Match URLs embedded in [imagemap] coordinate rows.
        if (preg_match_all('~\[imagemap\](.*?)\[/imagemap\]~is', $bbcode, $imagemaps)) {
            foreach ($imagemaps[1] as $imagemap) {
                if (preg_match_all('~https?://[^\s\]]+~i', $imagemap, $matches)) {
                    $urls = array_merge($urls, $matches[0]);
                }
            }
        }

        // Remove duplicates and return
        return array_values(array_unique($urls));
    }

    /**
     * Extract Discord URLs from content
     *
     * @return array<int, string>
     */
    public function extractDiscordUrls(string $bbcode): array
    {
        $urls = $this->extractUrls($bbcode);

        return array_filter($urls, function ($url) {
            return str_contains($url, 'discord.gg') || str_contains($url, 'discord.com/invite');
        });
    }

    /**
     * Extract Twitch URLs from content
     *
     * @return array<int, string>
     */
    public function extractTwitchUrls(string $bbcode): array
    {
        $urls = $this->extractUrls($bbcode);

        return array_filter($urls, function ($url) {
            return str_contains($url, 'twitch.tv');
        });
    }

    /**
     * Extract spreadsheet URLs from content
     *
     * @return array<int, string>
     */
    public function extractSpreadsheetUrls(string $bbcode): array
    {
        $urls = $this->extractUrls($bbcode);

        return array_filter($urls, function ($url) {
            return str_contains($url, 'docs.google.com/spreadsheets');
        });
    }

    /**
     * Extract bracket URLs from content
     *
     * @return array<int, string>
     */
    public function extractBracketUrls(string $bbcode): array
    {
        $urls = $this->extractUrls($bbcode);

        return array_filter($urls, function ($url) {
            return str_contains($url, 'challonge.com')
                || str_contains($url, 'brack.net')
                || str_contains($url, 'braacket.com');
        });
    }

    /**
     * Extract registration URLs from content
     *
     * @return array<int, string>
     */
    public function extractRegistrationUrls(string $bbcode): array
    {
        $urls = $this->extractUrls($bbcode);

        return array_filter($urls, function ($url) {
            return str_contains($url, 'forms.gle')
                || str_contains($url, 'docs.google.com/forms')
                || str_contains($url, 'typeform.com');
        });
    }
}
