<?php
/**
 * Database migration runner
 * Handles schema updates and migrations
 */

$dbPath = getenv('DB_PATH') ?: '/tmp/tournaments.db';
$migrationsDir = __DIR__ . '/../../data/migrations/';

echo "🔄 Running database migrations...\n";
echo "Database: {$dbPath}\n";

try {
    $pdo = new PDO("sqlite:{$dbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create migrations table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Get executed migrations
    $stmt = $pdo->query("SELECT migration FROM migrations ORDER BY id");
    $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "✅ Migrations table ready\n";
    
    // Find migration files
    if (is_dir($migrationsDir)) {
        $files = glob($migrationsDir . '*.sql');
        sort($files);
        
        $newMigrations = 0;
        foreach ($files as $file) {
            $filename = basename($file);
            
            if (!in_array($filename, $executed)) {
                echo "🔧 Running migration: {$filename}\n";
                
                $sql = file_get_contents($file);
                if ($sql) {
                    $pdo->exec($sql);
                    
                    // Record migration
                    $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
                    $stmt->execute([$filename]);
                    
                    $newMigrations++;
                    echo "✅ Migration {$filename} completed\n";
                }
            }
        }
        
        if ($newMigrations === 0) {
            echo "✅ No new migrations to run\n";
        } else {
            echo "✅ {$newMigrations} migrations executed successfully\n";
        }
    } else {
        echo "⚠️ Migrations directory not found: {$migrationsDir}\n";
        echo "✅ Skipping migrations (using base schema)\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "🎉 Migration process complete\n";