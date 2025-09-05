<?php

namespace TourneyMethod\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOException;

class DatabaseTest extends TestCase
{
    private ?PDO $pdo = null;
    private string $testDbPath;

    protected function setUp(): void
    {
        $this->testDbPath = TEST_DB_PATH;
        
        // Remove existing test database
        if (file_exists($this->testDbPath)) {
            @unlink($this->testDbPath);
        }
        
        // Create fresh test database
        $this->pdo = new PDO('sqlite:' . $this->testDbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Execute schema
        if (file_exists(SCHEMA_PATH)) {
            $schema = file_get_contents(SCHEMA_PATH);
            $this->pdo->exec($schema);
        }
    }

    protected function tearDown(): void
    {
        // Close PDO connection properly before unlinking file
        if ($this->pdo !== null) {
            $this->pdo = null;
        }
        
        // Add small delay to ensure file handle is released on Windows
        if (file_exists($this->testDbPath)) {
            usleep(10000); // 10ms delay
            @unlink($this->testDbPath); // Suppress warnings if file still locked
        }
    }

    public function testDatabaseConnection(): void
    {
        $this->assertInstanceOf(PDO::class, $this->pdo);
        $this->assertEquals('sqlite', $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function testRequiredTablesExist(): void
    {
        $expectedTables = [
            'users',
            'tournaments',
            'system_logs',
            'sessions',
            'api_rate_limits'
        ];

        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $actualTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($expectedTables as $table) {
            $this->assertContains($table, $actualTables, "Table '{$table}' should exist");
        }
    }

    public function testViewsExist(): void
    {
        $expectedViews = [
            'pending_tournaments',
            'approved_tournaments'
        ];

        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='view'");
        $actualViews = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($expectedViews as $view) {
            $this->assertContains($view, $actualViews, "View '{$view}' should exist");
        }
    }

    public function testUsersTableStructure(): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info(users)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $columnNames = array_column($columns, 'name');
        $expectedColumns = ['id', 'osu_id', 'username', 'is_admin', 'created_at', 'last_login'];
        
        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columnNames, "Column '{$column}' should exist in users table");
        }
    }

    public function testTournamentsTableStructure(): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info(tournaments)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $columnNames = array_column($columns, 'name');
        $expectedColumns = ['id', 'osu_topic_id', 'title', 'status', 'parsed_at'];
        
        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columnNames, "Column '{$column}' should exist in tournaments table");
        }
    }

    public function testForeignKeyConstraints(): void
    {
        // Test foreign key enforcement is enabled
        $stmt = $this->pdo->query("PRAGMA foreign_keys");
        $result = $stmt->fetch();
        $this->assertEquals('1', $result[0], 'Foreign keys should be enabled');
    }

    public function testBasicCrudOperations(): void
    {
        // Insert test user
        $stmt = $this->pdo->prepare("INSERT INTO users (osu_id, username, is_admin) VALUES (?, ?, ?)");
        $stmt->execute([12345, 'testuser', 0]);
        
        // Verify insertion
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE osu_id = ?");
        $stmt->execute([12345]);
        $user = $stmt->fetch();
        
        $this->assertNotFalse($user);
        $this->assertEquals('testuser', $user['username']);
        $this->assertEquals('0', $user['is_admin']); // SQLite returns strings
        
        // Insert test tournament
        $stmt = $this->pdo->prepare("INSERT INTO tournaments (osu_topic_id, title) VALUES (?, ?)");
        $stmt->execute([67890, 'Test Tournament']);
        
        // Verify tournament insertion
        $stmt = $this->pdo->prepare("SELECT * FROM tournaments WHERE osu_topic_id = ?");
        $stmt->execute([67890]);
        $tournament = $stmt->fetch();
        
        $this->assertNotFalse($tournament);
        $this->assertEquals('Test Tournament', $tournament['title']);
        $this->assertEquals('pending_review', $tournament['status']);
    }

    public function testIndexesExist(): void
    {
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND sql IS NOT NULL");
        $indexes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $expectedIndexes = [
            'idx_users_osu_id',
            'idx_tournaments_status',
            'idx_tournaments_topic_id'
        ];
        
        foreach ($expectedIndexes as $index) {
            $this->assertContains($index, $indexes, "Index '{$index}' should exist");
        }
    }
}