<?php
// lib/Utils.php

class Utils {
    public static function generateId(): string {
        return uniqid('test_') . '_' . bin2hex(random_bytes(8));
    }
    
    public static function sanitize($input): string {
        return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function log(string $message, string $file = 'debug.log'): void {
        $dir = __DIR__ . '/../logs';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/' . $file,
            date('Y-m-d H:i:s') . " - " . $message . "\n",
            FILE_APPEND
        );
    }
    
    public static function formatDate(string $date): string {
        if (empty($date)) return '';
        $dt = new DateTime($date);
        return $dt->format('d.m.Y H:i');
    }
}