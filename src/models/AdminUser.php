<?php

namespace TourneyMethod\Models;

/**
 * Admin User Model
 * 
 * Manages admin user validation, session handling, and authorization for the admin interface.
 * Uses hard-coded admin list for secure authorization validation.
 */
class AdminUser
{
    private int $userId;
    private int $osuId;
    private string $username;
    private bool $isAdmin;
    private \DateTime $createdAt;
    private ?\DateTime $updatedAt;
    
    /**
     * Hard-coded list of authorized admin osu! user IDs
     * 
     * @var array<int> Authorized admin osu! user IDs
     */
    private const AUTHORIZED_ADMINS = [
        // Add your osu! user ID here
        757783,
    ];
    
    /**
     * AdminUser constructor
     * 
     * @param int $userId Internal user ID
     * @param int $osuId osu! user ID
     * @param string $username osu! username
     * @param bool $isAdmin Admin flag
     * @param \DateTime|null $createdAt Creation timestamp
     * @param \DateTime|null $updatedAt Last update timestamp
     */
    public function __construct(
        int $userId,
        int $osuId,
        string $username,
        bool $isAdmin = false,
        ?\DateTime $createdAt = null,
        ?\DateTime $updatedAt = null
    ) {
        $this->userId = $userId;
        $this->osuId = $osuId;
        $this->username = $username;
        $this->isAdmin = $isAdmin;
        $this->createdAt = $createdAt ?: new \DateTime('now', new \DateTimeZone('Asia/Seoul'));
        $this->updatedAt = $updatedAt;
    }
    
    /**
     * Create AdminUser instance from osu! API user data
     * 
     * @param array $userData User data from osu! API
     * @return self AdminUser instance
     * @throws \InvalidArgumentException When user data is invalid
     */
    public static function fromOsuApiData(array $userData): self
    {
        if (!isset($userData['id']) || !isset($userData['username'])) {
            throw new \InvalidArgumentException('Invalid osu! user data: missing id or username');
        }
        
        $osuId = filter_var($userData['id'], FILTER_VALIDATE_INT);
        if ($osuId === false) {
            throw new \InvalidArgumentException('Invalid osu! user ID');
        }
        
        $username = filter_var($userData['username'], FILTER_SANITIZE_STRING);
        if (empty($username)) {
            throw new \InvalidArgumentException('Invalid osu! username');
        }
        
        $isAdmin = self::isAuthorizedAdmin($osuId);
        
        return new self(
            userId: 0, // Will be set when saved to database
            osuId: $osuId,
            username: $username,
            isAdmin: $isAdmin
        );
    }
    
    /**
     * Check if osu! user ID is in authorized admin list
     * 
     * @param int $osuId osu! user ID to check
     * @return bool True if user is authorized admin
     */
    public static function isAuthorizedAdmin(int $osuId): bool
    {
        return in_array($osuId, self::AUTHORIZED_ADMINS, true);
    }
    
    /**
     * Validate admin authorization and create session
     * 
     * @param array $userData User data from osu! API
     * @return self AdminUser instance if authorized
     * @throws \RuntimeException When user is not authorized as admin
     */
    public static function authorizeAdmin(array $userData): self
    {
        $adminUser = self::fromOsuApiData($userData);
        
        if (!$adminUser->isAdmin()) {
            throw new \RuntimeException('User is not authorized as admin');
        }
        
        return $adminUser;
    }
    
    /**
     * Create secure admin session
     * 
     * @return string Session ID
     * @throws \RuntimeException When session creation fails
     */
    public function createSession(): string
    {
        // Configure secure session parameters before starting session
        if (session_status() === PHP_SESSION_NONE) {
            // Only configure session settings if not in test environment
            if (!defined('PHPUNIT_RUNNING')) {
                ini_set('session.cookie_httponly', '1');
                ini_set('session.cookie_secure', '1');
                ini_set('session.cookie_samesite', 'Lax');
                ini_set('session.gc_maxlifetime', '3600'); // 1 hour
            }
            session_start();
        }
        
        // Store admin user data in session
        $_SESSION['admin_user'] = [
            'user_id' => $this->userId,
            'osu_id' => $this->osuId,
            'username' => $this->username,
            'is_admin' => $this->isAdmin,
            'login_time' => time(),
            'csrf_token' => bin2hex(random_bytes(32))
        ];
        
        return session_id();
    }
    
    /**
     * Get current authenticated admin user from session
     * 
     * @return self|null AdminUser if authenticated, null otherwise
     */
    public static function getCurrentUser(): ?self
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['admin_user'])) {
            return null;
        }
        
        $sessionData = $_SESSION['admin_user'];
        
        // Check session timeout (1 hour)
        if (!isset($sessionData['login_time']) || 
            (time() - $sessionData['login_time']) > 3600) {
            self::destroySession();
            return null;
        }
        
        return new self(
            userId: $sessionData['user_id'] ?? 0,
            osuId: $sessionData['osu_id'] ?? 0,
            username: $sessionData['username'] ?? '',
            isAdmin: $sessionData['is_admin'] ?? false
        );
    }
    
    /**
     * Check if current user is authenticated admin
     * 
     * @return bool True if authenticated as admin
     */
    public static function isAuthenticated(): bool
    {
        $currentUser = self::getCurrentUser();
        return $currentUser !== null && $currentUser->isAdmin();
    }
    
    /**
     * Destroy admin session and logout
     * 
     * @return void
     */
    public static function destroySession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear admin session data
        unset($_SESSION['admin_user']);
        
        // Destroy entire session
        session_destroy();
        
        // Clear session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }
    
    /**
     * Get CSRF token for current session
     * 
     * @return string CSRF token
     * @throws \RuntimeException When no active session
     */
    public static function getCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['admin_user']['csrf_token'])) {
            throw new \RuntimeException('No CSRF token found in session');
        }
        
        return $_SESSION['admin_user']['csrf_token'];
    }
    
    // Getters
    
    public function getUserId(): int
    {
        return $this->userId;
    }
    
    public function getOsuId(): int
    {
        return $this->osuId;
    }
    
    public function getUsername(): string
    {
        return $this->username;
    }
    
    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }
    
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
    
    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }
    
    /**
     * Convert to array for serialization
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'osu_id' => $this->osuId,
            'username' => $this->username,
            'is_admin' => $this->isAdmin,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Save user to database (upsert based on osu_id)
     * 
     * @param \PDO $db Database connection
     * @return int Database user ID
     */
    public function saveToDatabase(\PDO $db): int
    {
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE osu_id = ?");
        $stmt->execute([$this->osuId]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            // Update existing user
            $stmt = $db->prepare("
                UPDATE users 
                SET username = ?, is_admin = ?, last_login = datetime('now', '+9 hours')
                WHERE osu_id = ?
            ");
            $stmt->execute([$this->username, $this->isAdmin ? 1 : 0, $this->osuId]);
            
            // Update the userId property
            $this->userId = (int)$existingUser['id'];
            return $this->userId;
        } else {
            // Create new user
            $stmt = $db->prepare("
                INSERT INTO users (osu_id, username, is_admin, created_at, last_login) 
                VALUES (?, ?, ?, datetime('now', '+9 hours'), datetime('now', '+9 hours'))
            ");
            $stmt->execute([$this->osuId, $this->username, $this->isAdmin ? 1 : 0]);
            
            // Update the userId property
            $this->userId = (int)$db->lastInsertId();
            return $this->userId;
        }
    }
}