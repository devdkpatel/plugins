<?php
/*
Plugin Name: WP Daily Backup
Description: Custom backup solution for WordPress files and database daily, zips the whole WordPress site.
Version: 1.1
Author: Custom Backup Assistant
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WPDailyBackup {

    private $backup_dir;
    private $backup_log_option = 'wp_daily_backup_log';

    public function __construct() {
        // Backup directory inside wp-content
        $this->backup_dir = WP_CONTENT_DIR . '/backups';

        // Hook to initialize scheduling
        add_action('init', [$this, 'schedule_backup']);

        // Cron job hook
        add_action('wp_daily_backup_event', [$this, 'run_backup']);

        // Admin menu for logs
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Show admin notices for backup logs
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    public function schedule_backup() {
        if (!wp_next_scheduled('wp_daily_backup_event')) {
            // Schedule to run daily at 2am approx
            wp_schedule_event(strtotime('02:00:00'), 'daily', 'wp_daily_backup_event');
        }
    }

    public function run_backup() {
        global $wpdb;

        $date = date('Ymd_His');

        // Make sure backup directory exists
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
        }

        $logs = [];
        $logs[] = "Backup started at " . date('Y-m-d H:i:s');

        // Backup files (entire WordPress root directory except backup folder)
        $zip_files = $this->backup_files($date);
        if ($zip_files) {
            $logs[] = "Files backup success: $zip_files";
        } else {
            $logs[] = "Files backup failed!";
        }

        // Backup Database
        $sql_file = $this->backup_database($date, $wpdb);
        if ($sql_file) {
            $logs[] = "Database backup success: $sql_file";
        } else {
            $logs[] = "Database backup failed!";
        }

        // Cleanup old backups (older than 7 days)
        $this->cleanup_old_backups();

        // Save log
        $logs[] = "Backup finished at " . date('Y-m-d H:i:s');
        update_option($this->backup_log_option, implode("\n", $logs));
    }

    private function backup_files($date) {
        $zip_file = $this->backup_dir . "/wp_site_$date.zip";

        $root_path = ABSPATH; // WordPress root directory
        $backup_dir_realpath = realpath($this->backup_dir);

        $zip = new ZipArchive();

        if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            // Skip directories.
            if (!$file->isFile()) {
                continue;
            }

            $filePath = $file->getRealPath();

            // Skip the backup directory files to avoid backing up backups
            if (strpos($filePath, $backup_dir_realpath) === 0) {
                continue;
            }

            // Relative path inside zip
            $relativePath = substr($filePath, strlen($root_path));

            // Add file to zip archive
            $zip->addFile($filePath, $relativePath);
        }

        $zip->close();

        return file_exists($zip_file) ? $zip_file : false;
    }

    private function backup_database($date, $wpdb) {
        $sql_file = $this->backup_dir . "/wp_db_$date.sql";

        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        if (!$tables) {
            return false;
        }

        $handle = fopen($sql_file, 'w');
        if (!$handle) {
            return false;
        }

        fwrite($handle, "-- WordPress Database Backup\n");
        fwrite($handle, "-- Generation time: " . date('Y-m-d H:i:s') . "\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n\n");

        foreach ($tables as $table_arr) {
            $table = $table_arr[0];

            // Get create table statement
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($handle, $create_table[1] . ";\n\n");

            // Get table data
            $rows = $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A);
            if ($rows) {
                foreach ($rows as $row) {
                    $row_values = array_map(function($value) use ($wpdb) {
                        if (is_null($value)) {
                            return 'NULL';
                        }
                        return "'" . $wpdb->_real_escape($value) . "'";
                    }, array_values($row));
                    fwrite($handle, "INSERT INTO `$table` VALUES (" . implode(', ', $row_values) . ");\n");
                }
                fwrite($handle, "\n");
            }
        }

        fclose($handle);
        return file_exists($sql_file) ? $sql_file : false;
    }

    private function cleanup_old_backups() {
        $files = glob($this->backup_dir . '/*');

        $now = time();
        $days_to_keep = 7;
        $seconds_to_keep = 60 * 60 * 24 * $days_to_keep;

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) > $seconds_to_keep) {
                    @unlink($file);
                }
            }
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'WP Daily Backup Logs',
            'WP Backups',
            'manage_options',
            'wp-daily-backup-logs',
            [$this, 'render_admin_page'],
            'dashicons-backup',
            81
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $log = get_option($this->backup_log_option, 'No backups performed yet.');

        echo '<div class="wrap">';
        echo '<h1>WP Daily Backup Logs</h1>';
        echo '<pre style="background: #fff; border: 1px solid #ccc; padding: 10px;">' . esc_html($log) . '</pre>';
        echo '</div>';
    }

    public function admin_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_GET['page']) && $_GET['page'] === 'wp-daily-backup-logs') {
            return; // No notice on logs page
        }
        $last_log = get_option($this->backup_log_option);
        if (!$last_log) {
            return;
        }
        if (preg_match('/Backup finished at (.+)$/m', $last_log, $matches)) {
            echo '<div class="notice notice-success is-dismissible"><p>Last WordPress backup finished at ' . esc_html($matches[1]) . '.</p></div>';
        }
    }
}

// Initialize plugin
new WPDailyBackup();

?>