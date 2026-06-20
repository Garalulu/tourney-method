<?php

return [
    'hero' => [
        'title' => 'Tourney Method',
        'subtitle' => 'Discover osu! tournaments, track participation, and improve tournament data',
    ],
    'guest_actions' => [
        'browse_tournaments' => 'Browse Tournaments',
        'browse_tournaments_desc' => 'Explore tournaments from osu! forums',
        'login_to_search' => 'Login to Search & Filter',
        'login_to_search_desc' => 'Search users and check eligible tournaments',
    ],
    'auth_actions' => [
        'browse_tournaments' => 'Browse Tournaments',
        'browse_tournaments_desc' => 'Explore and filter tournaments',
        'already_logged_in' => 'Already Logged In',
        'already_logged_in_desc' => 'Use search and filters in the navigation',
    ],
    'sections' => [
        'tournament_discovery' => [
            'title' => 'Tournament Discovery',
            'content' => 'The tournament list is automatically updated from osu! tournament sources. Filter by game mode, registration status, rank requirements, region, and format to find tournaments you can enter.',
        ],
        'tournament_history' => [
            'title' => 'Tournament History',
            'content' => 'Each tournament page shows dates, eligibility, links, staff, podium results, and community corrections. If something is wrong, scroll to the bottom and use Correct Tournament.',
        ],
        'user_profiles' => [
            'title' => 'User Profiles',
            'content' => 'Your user profile keeps personal participation records, podiums, teammates, staff history, badges, and contribution history in one place.',
        ],
        'how_to_use' => [
            'title' => 'How to use Tourney Method',
            'items' => [
                0 => [
                    'title' => 'Find tournaments',
                    'body' => 'Use tournament filters and search commands to compare registration status, rank ranges, modes, regions, and formats.',
                ],
                1 => [
                    'title' => 'Watch registration',
                    'body' => 'When you watch tournaments, in-app notifications can alert you when eligible registration opens or closes within 24 hours.',
                ],
                2 => [
                    'title' => 'Track your history',
                    'body' => 'Open your profile and add participation records for tournaments you played, including stage reached, placement, teammates, and match links.',
                ],
                3 => [
                    'title' => 'Improve the data',
                    'body' => 'Open a tournament detail page, scroll to Contributions, and submit corrections for metadata, staff, podiums, or links.',
                ],
            ],
        ],
        'eligibility_checking' => [
            'title' => 'Eligibility Checking',
            'content' => 'Some tournaments use the Badge Weighted Seeding (BWS) system to determine eligibility. This system supports both standard and custom BWS formats, including methods that exclude older badges (e.g., badges earned before 2022 are excluded from the calculation), allowing you to see which tournaments you are currently eligible for based on various BWS calculation methods.',
        ],
        'data_sources' => [
            'title' => 'Data Sources',
            'osu_api' => 'osu! API',
            'osu_api_desc' => 'User authentication, profile data and tournament data',
            'tcomm' => 'tcomm API',
            'tcomm_desc' => 'Badged tournament data',
            'otr' => 'o!TR Public Datasets',
            'otr_desc' => 'Tournament data',
            'community' => 'Community',
            'community_desc' => 'Corrections, translations and participation records',
        ],
    ],
    'stats' => [
        'active_players' => 'Active Players',
        'active_staff' => 'Active Staff',
        'tournaments' => 'Tournaments',
    ],
];
