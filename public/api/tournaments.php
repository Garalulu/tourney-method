<?php
/**
 * Tourney Method - Tournament API Endpoint
 * REST API for tournament filtering and pagination
 */

// Set JSON content type
header('Content-Type: application/json; charset=utf-8');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS headers for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Include dependencies
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

use TourneyMethod\Models\Tournament;
use TourneyMethod\Utils\SecurityHelper;

try {
    
    // Initialize database
    initializeDatabase();
    setDatabasePermissions();
    
    $pdo = new PDO("sqlite:" . __DIR__ . "/../../data/tournament_method.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tournamentModel = new Tournament($pdo);
    
    // Parse and validate query parameters
    $filters = [];
    
    // Game mode filter
    if (!empty($_GET['mode'])) {
        $validModes = ['Standard', 'Taiko', 'Catch', 'Mania'];
        $mode = SecurityHelper::escapeHtml($_GET['mode']);
        if (in_array($mode, $validModes)) {
            $filters['game_mode'] = strtoupper($mode); // Convert to database format
        }
    }
    
    // Rank range filter
    if (!empty($_GET['rank_range']) && $_GET['rank_range'] !== 'All') {
        $rankRange = SecurityHelper::escapeHtml($_GET['rank_range']);
        if ($rankRange === 'Open') {
            // No rank restriction
        } else {
            // Convert format like "1k+" to database query
            $filters['rank_range'] = $rankRange;
        }
    }
    
    // Registration status filter
    if (!empty($_GET['status']) && $_GET['status'] !== 'All') {
        $status = SecurityHelper::escapeHtml($_GET['status']);
        $validStatuses = ['Open', 'Closed', 'Ongoing'];
        if (in_array($status, $validStatuses)) {
            $filters['registration_status'] = strtolower($status);
        }
    }
    
    // Search filter
    if (!empty($_GET['search'])) {
        $search = SecurityHelper::escapeHtml(trim($_GET['search']));
        if (strlen($search) > 0) {
            $filters['search'] = $search;
        }
    }
    
    // Pagination parameters
    $limit = 10; // Default
    if (!empty($_GET['limit'])) {
        $requestedLimit = filter_var($_GET['limit'], FILTER_VALIDATE_INT);
        if ($requestedLimit && in_array($requestedLimit, [10, 25, 50])) {
            $limit = $requestedLimit;
        }
    }
    
    $offset = 0;
    if (!empty($_GET['offset'])) {
        $requestedOffset = filter_var($_GET['offset'], FILTER_VALIDATE_INT);
        if ($requestedOffset && $requestedOffset >= 0) {
            $offset = $requestedOffset;
        }
    }
    
    // Get tournaments with filters
    $tournaments = $tournamentModel->getApprovedTournaments($limit, $offset, $filters);
    
    // Get total count for pagination info
    $totalCount = $tournamentModel->getCountByStatus('approved');
    
    // Process tournaments for frontend display
    $processedTournaments = [];
    foreach ($tournaments as $tournament) {
        $displayStatus = $tournamentModel->getTournamentDisplayStatus($tournament);
        
        $processedTournament = [
            'id' => (int)$tournament['id'],
            'title' => $tournament['title'],
            'host' => $tournament['host_name'] ?? 'Unknown Host',
            'mode' => $tournamentModel->formatGameMode($tournament['game_mode']),
            'rank_range' => $tournamentModel->formatRankRange($tournament),
            'team_info' => $tournamentModel->formatTeamInfo($tournament),
            'registration_status' => $displayStatus['text'],
            'status_class' => $displayStatus['class'],
            'created_at' => $tournament['parsed_at'],
            'banner_url' => $tournament['banner_url'] ?? null,
            'forum_url' => 'https://osu.ppy.sh/community/' . $tournament['forum_url_slug'],
            'discord_link' => $tournament['discord_link'],
            'google_form_id' => $tournament['google_form_id'],
            // Additional details for modal
            'tournament_start' => $tournament['tournament_start'],
            'registration_close' => $tournament['registration_close'],
            'end_date' => $tournament['end_date'],
            'is_bws' => (bool)$tournament['is_bws'],
            'has_badge' => (bool)$tournament['has_badge'],
            'star_rating_min' => $tournament['star_rating_min'],
            'star_rating_max' => $tournament['star_rating_max']
        ];
        
        $processedTournaments[] = $processedTournament;
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'tournaments' => $processedTournaments,
        'pagination' => [
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ],
        'filters' => $filters,
        'timestamp' => date('c')
    ];
    
    // Return JSON response
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Tournament API error: " . $e->getMessage());
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'error' => '토너먼트 데이터를 불러오는 중 오류가 발생했습니다.',
        'message' => $e->getMessage(),
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
}
?>