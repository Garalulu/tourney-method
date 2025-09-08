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
}