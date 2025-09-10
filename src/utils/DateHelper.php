<?php

namespace TourneyMethod\Utils;

class DateHelper
{
    /**
     * Format datetime to Korean Standard Time (KST)
     */
    public static function formatToKST(string $datetime): string
    {
        $dt = new \DateTime($datetime, new \DateTimeZone('UTC'));
        $dt->setTimezone(new \DateTimeZone('Asia/Seoul'));
        
        return $dt->format('Y-m-d H:i:s');
    }
    
    /**
     * Format datetime to Korean date only
     */
    public static function formatToKSTDate(string $datetime): string
    {
        $dt = new \DateTime($datetime, new \DateTimeZone('UTC'));
        $dt->setTimezone(new \DateTimeZone('Asia/Seoul'));
        
        return $dt->format('Y-m-d');
    }

    
    /**
     * Format datetime that's already in KST timezone (no conversion needed)
     * Use this for database timestamps that are stored in KST
     */
    public static function formatKST(string $datetime): string
    {
        // Assume input is already in KST, just format it
        $dt = new \DateTime($datetime, new \DateTimeZone('Asia/Seoul'));
        return $dt->format('Y-m-d H:i:s');
    }
    
    /**
     * Format datetime that's already in KST to date only
     */
    public static function formatKSTDate(string $datetime): string
    {
        $dt = new \DateTime($datetime, new \DateTimeZone('Asia/Seoul'));
        return $dt->format('Y-m-d');
    }
}