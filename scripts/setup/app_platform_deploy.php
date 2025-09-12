<?php
/**
 * App Platform deployment initialization script
 * Runs automatically on each deployment
 */

echo "🚀 Initializing Tourney Method for App Platform deployment...\n";

// Create necessary directories
$dirs = ['/tmp/backups', '/tmp/logs'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✅ Created directory: {$dir}\n";
    }
}

// Initialize SQLite database if not exists
$dbPath = getenv('DB_PATH') ?: '/tmp/tournaments.db';
if (!file_exists($dbPath)) {
    echo "🔧 Initializing SQLite database...\n";
    
    // Create database with schema
    $schemaPath = __DIR__ . '/../../data/database/schema.sql';
    if (file_exists($schemaPath)) {
        $schema = file_get_contents($schemaPath);
        $pdo = new PDO("sqlite:{$dbPath}");
        $pdo->exec($schema);
        
        // Set proper permissions
        chmod($dbPath, 0600);
        
        echo "✅ Database initialized at {$dbPath}\n";
    } else {
        echo "⚠️ Schema file not found at {$schemaPath}\n";
    }
} else {
    echo "✅ Database already exists\n";
}

// Run any pending migrations
echo "🔧 Running database migrations...\n";
$migratePath = __DIR__ . '/migrate.php';
if (file_exists($migratePath)) {
    include $migratePath;
} else {
    echo "⚠️ Migration script not found, skipping...\n";
}

// Korean-specific setup
echo "🇰🇷 Applying Korean market optimizations...\n";

// Set timezone
$timezone = getenv('TZ') ?: 'Asia/Seoul';
date_default_timezone_set($timezone);

// Verify UTF-8 support
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
    echo "✅ UTF-8 encoding configured for Korean support\n";
}

// Create initial admin user if not exists (ID: 757783)
try {
    $pdo = new PDO("sqlite:{$dbPath}");
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE osu_user_id = ?");
    $stmt->execute([757783]);

    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO admin_users (osu_user_id, role, created_at) VALUES (?, ?, ?)");
        $stmt->execute([757783, 'super_admin', date('Y-m-d H:i:s')]);
        echo "✅ Admin user 757783 configured\n";
    } else {
        echo "✅ Admin user already exists\n";
    }
} catch (Exception $e) {
    echo "⚠️ Could not configure admin user: " . $e->getMessage() . "\n";
}

echo "🎉 App Platform deployment complete!\n";
echo "🇰🇷 Ready to serve Korean osu! tournament community\n";