<?php
/**
 * Finix Custom Logger
 *
 * Dedicated logging system for Finix plugin that bypasses wp-debug.log
 * to avoid memory conflicts with other plugins.
 *
 * @package Finix_WC_Subs
 * @since 1.8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Finix_Logger {

    /**
     * Log file path
     */
    private static $log_file = null;

    /**
     * Maximum log file size (5MB)
     */
    const MAX_LOG_SIZE = 5242880;

    /**
     * Whether logging is enabled
     */
    private static $enabled = null;

    /**
     * Initialize logger
     */
    public static function init() {
        if (self::$log_file === null) {
            $upload_dir = wp_upload_dir();
            self::$log_file = $upload_dir['basedir'] . '/finix-debug.log';
        }

        // Check if logging is enabled in settings
        if (self::$enabled === null) {
            $card_settings = get_option('woocommerce_finix_gateway_settings', array());
            $bank_settings = get_option('woocommerce_finix_bank_gateway_settings', array());

            // Enable if either gateway has debug enabled
            self::$enabled = (
                (!empty($card_settings['debug']) && $card_settings['debug'] === 'yes') ||
                (!empty($bank_settings['debug']) && $bank_settings['debug'] === 'yes')
            );
        }
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    public static function is_enabled() {
        self::init();
        return self::$enabled;
    }

    /**
     * Enable logging
     */
    public static function enable() {
        self::init();
        self::$enabled = true;
    }

    /**
     * Disable logging
     */
    public static function disable() {
        self::init();
        self::$enabled = false;
    }

    /**
     * Write log entry
     *
     * @param string $level Log level (INFO, ERROR, DEBUG, API)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function log($level, $message, $context = array()) {
        self::init();

        if (!self::$enabled) {
            return;
        }

        // Rotate log if too large
        self::maybe_rotate_log();

        // Format timestamp
        $timestamp = gmdate('Y-m-d H:i:s');

        // Format message
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            $level,
            $message
        );

        // Add context if provided
        if (!empty($context)) {
            $log_entry .= "Context: " . print_r($context, true) . "\n";
        }

        $log_entry .= str_repeat('-', 80) . "\n";

        // Write to file
        error_log($log_entry, 3, self::$log_file);
    }

    /**
     * Log info message
     *
     * @param string $message
     * @param array $context
     */
    public static function info($message, $context = array()) {
        self::log('INFO', $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     */
    public static function error($message, $context = array()) {
        self::log('ERROR', $message, $context);
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array $context
     */
    public static function debug($message, $context = array()) {
        self::log('DEBUG', $message, $context);
    }

    /**
     * Log API request/response
     *
     * @param string $message
     * @param array $context
     */
    public static function api($message, $context = array()) {
        self::log('API', $message, $context);
    }

    /**
     * Log JavaScript console messages
     *
     * @param string $message
     * @param string $level (log, warn, error)
     * @param array $context
     */
    public static function console($message, $level = 'log', $context = array()) {
        self::log('JS-' . strtoupper($level), $message, $context);
    }

    /**
     * Rotate log file if too large
     */
    private static function maybe_rotate_log() {
        if (file_exists(self::$log_file) && filesize(self::$log_file) > self::MAX_LOG_SIZE) {
            $backup_file = self::$log_file . '.old';

            // Remove old backup if exists
            if (file_exists($backup_file)) {
                unlink($backup_file);
            }

            // Rename current log to backup
            rename(self::$log_file, $backup_file);
        }
    }

    /**
     * Get log file path
     *
     * @return string
     */
    public static function get_log_file_path() {
        self::init();
        return self::$log_file;
    }

    /**
     * Get log file contents
     *
     * @param int $lines Number of lines to retrieve (default: all)
     * @return string
     */
    public static function get_log_contents($lines = 0) {
        self::init();

        if (!file_exists(self::$log_file)) {
            return "Log file not found. No errors logged yet.";
        }

        if ($lines > 0) {
            // Get last N lines
            $file = file(self::$log_file);
            $file = array_slice($file, -$lines);
            return implode('', $file);
        }

        return file_get_contents(self::$log_file);
    }

    /**
     * Clear log file
     */
    public static function clear_log() {
        self::init();

        if (file_exists(self::$log_file)) {
            unlink(self::$log_file);
        }

        $backup_file = self::$log_file . '.old';
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
    }

    /**
     * Get log file size
     *
     * @return int Size in bytes
     */
    public static function get_log_size() {
        self::init();

        if (file_exists(self::$log_file)) {
            return filesize(self::$log_file);
        }

        return 0;
    }

    /**
     * Format bytes to human readable size
     *
     * @param int $bytes
     * @return string
     */
    public static function format_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
