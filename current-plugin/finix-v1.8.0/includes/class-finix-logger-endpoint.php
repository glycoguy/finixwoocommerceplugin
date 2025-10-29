<?php
/**
 * Finix Logger Endpoint
 *
 * AJAX endpoint for JavaScript console logging and log viewing
 *
 * @package Finix_WC_Subs
 * @since 1.8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class Finix_Logger_Endpoint {

    /**
     * Initialize endpoint
     */
    public static function init() {
        // AJAX endpoint for JS logging (both logged-in and guest users)
        add_action('wp_ajax_finix_log_console', array(__CLASS__, 'handle_console_log'));
        add_action('wp_ajax_nopriv_finix_log_console', array(__CLASS__, 'handle_console_log'));

        // Admin AJAX endpoints for log management
        add_action('wp_ajax_finix_view_log', array(__CLASS__, 'handle_view_log'));
        add_action('wp_ajax_finix_clear_log', array(__CLASS__, 'handle_clear_log'));
        add_action('wp_ajax_finix_download_log', array(__CLASS__, 'handle_download_log'));

        // Add admin menu for log viewer
        add_action('admin_menu', array(__CLASS__, 'add_log_viewer_menu'), 99);
    }

    /**
     * Handle console log AJAX request
     */
    public static function handle_console_log() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'finix_logging')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Get log data
        $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : 'log';
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : array();

        // Add request URL if available
        if (isset($_POST['url'])) {
            $context['url'] = esc_url_raw($_POST['url']);
        }

        // Log the console message
        Finix_Logger::console($message, $level, $context);

        wp_send_json_success('Logged');
    }

    /**
     * Handle view log AJAX request
     */
    public static function handle_view_log() {
        // Check admin permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
            return;
        }

        check_ajax_referer('finix_log_viewer', 'nonce');

        $lines = isset($_POST['lines']) ? intval($_POST['lines']) : 0;
        $contents = Finix_Logger::get_log_contents($lines);
        $size = Finix_Logger::get_log_size();
        $formatted_size = Finix_Logger::format_size($size);

        wp_send_json_success(array(
            'contents' => $contents,
            'size' => $size,
            'formatted_size' => $formatted_size,
            'path' => Finix_Logger::get_log_file_path(),
        ));
    }

    /**
     * Handle clear log AJAX request
     */
    public static function handle_clear_log() {
        // Check admin permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
            return;
        }

        check_ajax_referer('finix_log_viewer', 'nonce');

        Finix_Logger::clear_log();

        wp_send_json_success('Log cleared');
    }

    /**
     * Handle download log request
     */
    public static function handle_download_log() {
        // Check admin permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permission denied');
        }

        check_ajax_referer('finix_log_viewer', 'nonce');

        $log_file = Finix_Logger::get_log_file_path();

        if (!file_exists($log_file)) {
            wp_die('Log file not found');
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="finix-debug-' . date('Y-m-d-His') . '.log"');
        header('Content-Length: ' . filesize($log_file));
        readfile($log_file);
        exit;
    }

    /**
     * Add log viewer menu to admin
     */
    public static function add_log_viewer_menu() {
        add_submenu_page(
            'woocommerce',
            'Finix Debug Log',
            'Finix Debug Log',
            'manage_woocommerce',
            'finix-debug-log',
            array(__CLASS__, 'render_log_viewer_page')
        );
    }

    /**
     * Render log viewer page
     */
    public static function render_log_viewer_page() {
        $log_enabled = Finix_Logger::is_enabled();
        $log_size = Finix_Logger::format_size(Finix_Logger::get_log_size());
        $log_path = Finix_Logger::get_log_file_path();
        ?>
        <div class="wrap">
            <h1>Finix Debug Log</h1>

            <?php if (!$log_enabled): ?>
                <div class="notice notice-warning">
                    <p><strong>Debug logging is currently disabled.</strong></p>
                    <p>To enable logging, go to <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=finix_gateway'); ?>">WooCommerce &gt; Settings &gt; Payments &gt; Finix Card Gateway</a> and enable the "Debug Mode" option.</p>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2>Log Information</h2>
                <table class="widefat">
                    <tr>
                        <th style="width: 200px;">Log File Path:</th>
                        <td><code><?php echo esc_html($log_path); ?></code></td>
                    </tr>
                    <tr>
                        <th>Current Size:</th>
                        <td><?php echo esc_html($log_size); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <?php if ($log_enabled): ?>
                                <span style="color: green;">✓ Enabled</span>
                            <?php else: ?>
                                <span style="color: red;">✗ Disabled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h2>Actions</h2>
                <p>
                    <button id="finix-refresh-log" class="button button-primary">Refresh Log</button>
                    <button id="finix-download-log" class="button">Download Log</button>
                    <button id="finix-clear-log" class="button button-secondary">Clear Log</button>
                    <label style="margin-left: 20px;">
                        Show last:
                        <select id="finix-log-lines">
                            <option value="0">All Lines</option>
                            <option value="50">50 Lines</option>
                            <option value="100" selected>100 Lines</option>
                            <option value="200">200 Lines</option>
                            <option value="500">500 Lines</option>
                        </select>
                    </label>
                    <label style="margin-left: 10px;">
                        <input type="checkbox" id="finix-auto-refresh"> Auto-refresh (5s)
                    </label>
                </p>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h2>Log Contents</h2>
                <pre id="finix-log-contents" style="background: #f5f5f5; padding: 15px; max-height: 600px; overflow-y: auto; font-size: 12px; line-height: 1.4;">Loading...</pre>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var autoRefreshInterval = null;

            function refreshLog() {
                var lines = $('#finix-log-lines').val();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'finix_view_log',
                        nonce: '<?php echo wp_create_nonce('finix_log_viewer'); ?>',
                        lines: lines
                    },
                    success: function(response) {
                        if (response.success) {
                            var contents = response.data.contents || 'Log is empty';
                            $('#finix-log-contents').text(contents);
                            $('#finix-log-contents').scrollTop($('#finix-log-contents')[0].scrollHeight);
                        } else {
                            $('#finix-log-contents').text('Error loading log: ' + response.data);
                        }
                    },
                    error: function() {
                        $('#finix-log-contents').text('Error loading log');
                    }
                });
            }

            $('#finix-refresh-log').on('click', refreshLog);

            $('#finix-log-lines').on('change', refreshLog);

            $('#finix-download-log').on('click', function() {
                window.location.href = ajaxurl + '?action=finix_download_log&nonce=<?php echo wp_create_nonce('finix_log_viewer'); ?>';
            });

            $('#finix-clear-log').on('click', function() {
                if (!confirm('Are you sure you want to clear the log file? This cannot be undone.')) {
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'finix_clear_log',
                        nonce: '<?php echo wp_create_nonce('finix_log_viewer'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Log cleared successfully');
                            refreshLog();
                        } else {
                            alert('Error clearing log: ' + response.data);
                        }
                    }
                });
            });

            $('#finix-auto-refresh').on('change', function() {
                if ($(this).is(':checked')) {
                    autoRefreshInterval = setInterval(refreshLog, 5000);
                } else {
                    if (autoRefreshInterval) {
                        clearInterval(autoRefreshInterval);
                        autoRefreshInterval = null;
                    }
                }
            });

            // Initial load
            refreshLog();
        });
        </script>
        <?php
    }
}
