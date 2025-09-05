<?php

namespace TourneyMethod\Utils;

/**
 * Simple .env file loader for development
 * 
 * Loads environment variables from .env file for local development.
 * In production, environment variables should be set by the platform.
 */
class EnvLoader
{
    /**
     * Load environment variables from .env file
     * 
     * @param string $filePath Path to .env file
     * @return void
     */
    public static function load(string $filePath = null): void
    {
        if ($filePath === null) {
            $filePath = dirname(__DIR__, 2) . '/.env';
        }
        
        if (!file_exists($filePath)) {
            return; // No .env file, assume production environment
        }
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            return;
        }
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            
            // Parse KEY=VALUE format
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes from value if present
                if (strlen($value) >= 2) {
                    $firstChar = $value[0];
                    $lastChar = $value[strlen($value) - 1];
                    if (($firstChar === '"' && $lastChar === '"') || 
                        ($firstChar === "'" && $lastChar === "'")) {
                        $value = substr($value, 1, -1);
                    }
                }
                
                // Set environment variable if not already set
                if (getenv($key) === false) {
                    putenv($key . '=' . $value);
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}