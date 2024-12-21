<?php
/**
 * Plugin Name:         ArrayPress - IPQualityScore Basic Tester
 * Plugin URI:          https://github.com/arraypress/ipqualityscore-plugin
 * Description:         A plugin to test IPQualityScore IP and Email validation functionality.
 * Author:              ArrayPress
 * Author URI:          https://arraypress.com
 * License:             GNU General Public License v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         arraypress-ipqualityscore
 * Domain Path:         /languages/
 * Requires PHP:        7.4
 * Requires at least:   6.7.1
 * Version:             1.0.0
 */

namespace ArrayPress\IPQualityScore;

use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Include required files and initialize the Plugin class if available.
 */
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Plugin class to handle all the functionality
 */
class Plugin {

	/**
	 * Instance of IPQualityScore Client
	 *
	 * @var Client|null
	 */
	private ?Client $client = null;

	/**
	 * Initialize the plugin
	 */
	public function __construct() {
		// Initialize client if key is set
		$key = get_option( 'ipqualityscore_api_key' );
		if ( $key ) {
			$this->client = new Client( $key, false );
		}

		// Hook into WordPress
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );

		// Add AJAX handlers
		add_action( 'wp_ajax_ipqs_report_fraud', [ $this, 'handle_report_fraud' ] );
	}

	/**
	 * Handle fraud report AJAX request
	 */
	public function handle_report_fraud() {
		check_ajax_referer( 'ipqs_report_fraud', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$request_id = sanitize_text_field( $_POST['request_id'] );

		try {
			$result = $this->client->report_request( $request_id );
			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Enqueue admin styles
	 */
	public function enqueue_admin_styles( $hook ) {
		if ( 'tools_page_ipqualityscore-tester' !== $hook ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', '
            .ipqs-field-group select {
                width: 100%;
                max-width: 500px;
                margin-bottom: 8px;
            }
            .ipqs-field-group label {
                display: block;
                margin: 10px 0 5px;
            }
            .ipqs-field-group p.description {
                margin-top: 4px;
                margin-bottom: 15px;
                color: #666;
            }
            .ipqs-results {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                margin: 20px 0;
                padding: 15px;
            }
            .ipqs-tabs { margin-bottom: 20px; }
            .ipqs-tab-content { display: none; }
            .ipqs-tab-content.active { display: block; }
            .ipqs-card {
                background: #fff;
                border: 1px solid #e5e5e5;
                padding: 15px;
                margin-bottom: 15px;
            }
            .ipqs-status {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 3px;
                font-weight: 500;
            }
            .ipqs-status-safe { background: #d1e7dd; color: #0a3622; }
            .ipqs-status-warning { background: #fff3cd; color: #664d03; }
            .ipqs-status-danger { background: #f8d7da; color: #842029; }
            .ipqs-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
        ' );
	}

	/**
	 * Add admin menu pages
	 */
	public function add_admin_menu() {
		add_management_page(
			'IPQualityScore Tester',
			'IPQualityScore Tester',
			'manage_options',
			'ipqualityscore-tester',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting( 'ipqualityscore_settings', 'ipqualityscore_api_key' );

		add_settings_section(
			'ipqualityscore_settings_section',
			'API Settings',
			null,
			'ipqualityscore-tester'
		);

		add_settings_field(
			'ipqualityscore_api_key',
			'IPQualityScore API Key',
			[ $this, 'render_key_field' ],
			'ipqualityscore-tester',
			'ipqualityscore_settings_section'
		);
	}

	/**
	 * Render API key field
	 */
	public function render_key_field() {
		$key = get_option( 'ipqualityscore_api_key' );
		echo '<input type="text" name="ipqualityscore_api_key" value="' . esc_attr( $key ) . '" class="regular-text">';
		echo '<p class="description">Enter your IPQualityScore API key</p>';
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		if ( ! $this->client ) {
			$this->render_no_api_key_message();

			return;
		}

		// Get current test type
		$test_type = $_POST['test_type'] ?? 'ip';

		// Handle form submission
		if ( isset( $_POST['submit'] ) ) {
			$this->handle_form_submission( $test_type );
		}

		?>
        <div class="wrap">
            <h1>IPQualityScore Tester</h1>

			<?php
			// Settings Form
			$this->render_settings_form();

			// Credit Usage
			$this->render_credit_usage();

			// Test Interface
			$this->render_test_interface( $test_type );
			?>
        </div>

		<?php $this->render_js(); ?>
		<?php
	}

	/**
	 * Render message when no API key is set
	 */
	private function render_no_api_key_message() {
		?>
        <div class="wrap">
            <h1>IPQualityScore Tester</h1>
            <div class="notice notice-error">
                <p>Please configure your IPQualityScore API key in the settings below to use this tool.</p>
            </div>
			<?php $this->render_settings_form(); ?>
        </div>
		<?php
	}

	/**
	 * Render settings form
	 */
	private function render_settings_form() {
		?>
        <div class="ipqs-card">
            <h2>Settings</h2>
            <form method="post" action="options.php">
				<?php
				settings_fields( 'ipqualityscore_settings' );
				do_settings_sections( 'ipqualityscore-tester' );
				submit_button( 'Save Settings' );
				?>
            </form>
        </div>
		<?php
	}

	/**
	 * Render test interface
	 */
	private function render_test_interface( $current_test_type ) {
		?>
        <div class="ipqs-section">
            <h2>Test Tools</h2>

            <div class="ipqs-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#" class="nav-tab <?php echo $current_test_type === 'ip' ? 'nav-tab-active' : ''; ?>"
                       data-tab="ip">
                        IP Analysis
                    </a>
                    <a href="#" class="nav-tab <?php echo $current_test_type === 'email' ? 'nav-tab-active' : ''; ?>"
                       data-tab="email">
                        Email Validation
                    </a>
                    <a href="#" class="nav-tab <?php echo $current_test_type === 'phone' ? 'nav-tab-active' : ''; ?>"
                       data-tab="phone">
                        Phone Validation
                    </a>
                    <a href="#" class="nav-tab <?php echo $current_test_type === 'leak' ? 'nav-tab-active' : ''; ?>"
                       data-tab="leak">
                        Data Leak Check
                    </a>
                    <a href="#" class="nav-tab <?php echo $current_test_type === 'url' ? 'nav-tab-active' : ''; ?>"
                       data-tab="url">
                        URL Scanner
                    </a>
                    <a href="#" class="nav-tab <?php echo $current_test_type === 'history' ? 'nav-tab-active' : ''; ?>"
                       data-tab="history">
                        Request History
                    </a>
<!--                    <a href="#" class="nav-tab --><?php //echo $current_test_type === 'lists' ? 'nav-tab-active' : ''; ?><!--"-->
<!--                       data-tab="lists">-->
<!--                        Allow/Block Lists-->
<!--                    </a>-->
                </nav>
            </div>

            <!-- Existing Tabs -->
            <div class="ipqs-tab-content <?php echo $current_test_type === 'ip' ? 'active' : ''; ?>" id="tab-ip">
				<?php $this->render_ip_test_form(); ?>
            </div>

            <div class="ipqs-tab-content <?php echo $current_test_type === 'email' ? 'active' : ''; ?>" id="tab-email">
				<?php $this->render_email_test_form(); ?>
            </div>

            <div class="ipqs-tab-content <?php echo $current_test_type === 'phone' ? 'active' : ''; ?>" id="tab-phone">
				<?php $this->render_phone_test_form(); ?>
            </div>

            <div class="ipqs-tab-content <?php echo $current_test_type === 'leak' ? 'active' : ''; ?>" id="tab-leak">
				<?php $this->render_leak_check_form(); ?>
            </div>

            <!-- New URL Scanner Tab -->
            <div class="ipqs-tab-content <?php echo $current_test_type === 'url' ? 'active' : ''; ?>" id="tab-url">
				<?php $this->render_url_scanner_form(); ?>
            </div>

            <!-- New Request History Tab -->
            <div class="ipqs-tab-content <?php echo $current_test_type === 'history' ? 'active' : ''; ?>"
                 id="tab-history">
				<?php $this->render_request_history(); ?>
            </div>

            <!-- New Allow/Block Lists Tab -->
            <div class="ipqs-tab-content <?php echo $current_test_type === 'lists' ? 'active' : ''; ?>" id="tab-lists">
				<?php $this->render_lists_management(); ?>
            </div>
        </div>
		<?php
	}

	/**
	 * Render URL scanner form
	 */
	private function render_url_scanner_form() {
		?>
        <form method="post" class="ipqs-card">
            <input type="hidden" name="test_type" value="url">

            <div class="ipqs-field-group">
                <label for="url">URL to Scan:</label>
                <input type="url" name="url" id="url" class="regular-text" required>
                <p class="description">Enter a URL to scan for malware, phishing, and other threats</p>
            </div>

            <div class="ipqs-field-group">
                <h4>Scan Options:</h4>
                <label>
                    <input type="checkbox" name="options[]" value="malware">
                    Check for Malware
                </label><br>
                <label>
                    <input type="checkbox" name="options[]" value="phishing">
                    Check for Phishing
                </label>
            </div>

			<?php submit_button( 'Scan URL', 'primary', 'submit' ); ?>
        </form>
		<?php
	}

	/**
	 * Render request history
	 */
	private function render_request_history() {
		// Get request type and page from GET parameters
		$request_type = $_GET['request_type'] ?? 'proxy';
		$page         = max( 1, intval( $_GET['page'] ?? 1 ) );

		try {
			$history = $this->client->get_request_list( $request_type, [ 'page' => $page ] );

			if ( is_wp_error( $history ) ) {
				$this->render_error( $history->get_error_message() );

				return;
			}

			?>
            <div class="ipqs-card">
                <h3>Request History</h3>

                <!-- Request Type Filter -->
                <form method="get" action="">
                    <input type="hidden" name="page" value="ipqualityscore-tester">
                    <input type="hidden" name="tab" value="history">

                    <div class="ipqs-field-group">
                        <label for="request_type">Request Type:</label>
                        <select name="request_type" id="request_type">
                            <option value="proxy" <?php selected( $request_type, 'proxy' ); ?>>Proxy Detection</option>
                            <option value="email" <?php selected( $request_type, 'email' ); ?>>Email Validation</option>
                            <option value="devicetracker" <?php selected( $request_type, 'devicetracker' ); ?>>Device
                                Tracking
                            </option>
                            <option value="mobiletracker" <?php selected( $request_type, 'mobiletracker' ); ?>>Mobile
                                Tracking
                            </option>
                        </select>
                    </div>

					<?php submit_button( 'View History', 'secondary', 'submit', false ); ?>
                </form>

                <!-- Results Table -->
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Timestamp</th>
                        <th>Value</th>
                        <th>Fraud Score</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
					<?php foreach ( $history->get_requests() as $request ): ?>
                        <tr>
                            <td><?php echo esc_html( $request['request_id'] ); ?></td>
                            <td><?php echo esc_html( $request['click_date'] ); ?></td>
                            <td>
								<?php
								// Handle the value display
								$value = 'N/A';
								if ( ! empty( $request['variables'] ) && is_array( $request['variables'] ) ) {
									foreach ( $request['variables'] as $variable ) {
										if ( $variable['name'] === $request_type ) {
											$value = $variable['value'];
											break;
										}
									}
								}
								// Fallback to email if it exists directly in the request
								if ( $value === 'N/A' && isset( $request['email'] ) ) {
									$value = $request['email'];
								}
								echo esc_html( $value );
								?>
                            </td>
                            <td><?php echo isset( $request['fraud_score'] ) ? esc_html( $request['fraud_score'] ) : 'N/A'; ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="report_fraud">
                                    <input type="hidden" name="request_id"
                                           value="<?php echo esc_attr( $request['request_id'] ); ?>">
                                    <button type="submit" class="button button-small">Report Fraud</button>
                                </form>
                            </td>
                        </tr>
					<?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
				<?php if ( $history->get_total_pages() > 1 ): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
							<?php
							echo paginate_links( [
								'base'      => add_query_arg( [ 'paged' => '%#%', 'request_type' => $request_type ] ),
								'format'    => '',
								'prev_text' => __( '&laquo;' ),
								'next_text' => __( '&raquo;' ),
								'total'     => $history->get_total_pages(),
								'current'   => $page
							] );
							?>
                        </div>
                    </div>
				<?php endif; ?>
            </div>
			<?php
		} catch ( Exception $e ) {
			$this->render_error( $e->getMessage() );
		}
	}

	/**
	 * Render lists management interface
	 */
	private function render_lists_management() {
		?>
        <div class="ipqs-card">
            <h3>Allow/Block Lists Management</h3>

            <!-- List Type Tabs -->
            <div class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active" data-list="allow">Allow List</a>
                <a href="#" class="nav-tab" data-list="block">Block List</a>
            </div>

            <!-- Allow List Section -->
            <div class="ipqs-list-content active" id="allow-list">
                <h4>Add to Allow List</h4>
                <form method="post" class="ipqs-form">
                    <input type="hidden" name="action" value="add_allowlist">

                    <div class="ipqs-field-group">
                        <label for="allow_type">Type:</label>
                        <select name="type" id="allow_type" required>
                            <option value="proxy">Proxy Detection</option>
                            <option value="url">URL Scanning</option>
                            <option value="email">Email Validation</option>
                            <option value="phone">Phone Validation</option>
                        </select>
                    </div>

                    <div class="ipqs-field-group">
                        <label for="allow_value_type">Value Type:</label>
                        <select name="value_type" id="allow_value_type" required>
                            <option value="ip">IP Address</option>
                            <option value="cidr">CIDR Range</option>
                            <option value="email">Email</option>
                            <option value="domain">Domain</option>
                            <option value="phone">Phone Number</option>
                        </select>
                    </div>

                    <div class="ipqs-field-group">
                        <label for="allow_value">Value:</label>
                        <input type="text" name="value" id="allow_value" required>
                    </div>

                    <div class="ipqs-field-group">
                        <label for="allow_reason">Reason (Optional):</label>
                        <input type="text" name="reason" id="allow_reason">
                    </div>

					<?php submit_button( 'Add to Allow List' ); ?>
                </form>

                <!-- Allow List Table -->
				<?php $this->render_list_entries( 'allow' ); ?>
            </div>

            <!-- Block List Section -->
            <div class="ipqs-list-content" id="block-list" style="display: none;">
                <h4>Add to Block List</h4>
                <form method="post" class="ipqs-form">
                    <input type="hidden" name="action" value="add_blocklist">

                    <!-- Similar form fields as allow list -->
                    <div class="ipqs-field-group">
                        <label for="block_type">Type:</label>
                        <select name="type" id="block_type" required>
                            <option value="proxy">Proxy Detection</option>
                            <option value="url">URL Scanning</option>
                            <option value="email">Email Validation</option>
                            <option value="phone">Phone Validation</option>
                        </select>
                    </div>

                    <div class="ipqs-field-group">
                        <label for="block_value_type">Value Type:</label>
                        <select name="value_type" id="block_value_type" required>
                            <option value="ip">IP Address</option>
                            <option value="cidr">CIDR Range</option>
                            <option value="email">Email</option>
                            <option value="domain">Domain</option>
                            <option value="phone">Phone Number</option>
                        </select>
                    </div>

                    <div class="ipqs-field-group">
                        <label for="block_value">Value:</label>
                        <input type="text" name="value" id="block_value" required>
                    </div>

                    <div class="ipqs-field-group">
                        <label for="block_reason">Reason (Optional):</label>
                        <input type="text" name="reason" id="block_reason">
                    </div>

					<?php submit_button( 'Add to Block List' ); ?>
                </form>

                <!-- Block List Table -->
				<?php $this->render_list_entries( 'block' ); ?>
            </div>
        </div>
		<?php
	}

	/**
	 * Render list entries table
	 */
	private function render_list_entries( $list_type ) {
		try {
			$entries = $list_type === 'allow' ?
				$this->client->get_allowlist_entries() :
				$this->client->get_blocklist_entries();

			if ( is_wp_error( $entries ) ) {
				$this->render_error( $entries->get_error_message() );

				return;
			}
			?>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th>Value</th>
                    <th>Type</th>
                    <th>Value Type</th>
                    <th>Added</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( $entries->get_entries() as $entry ): ?>
                    <tr>
                        <td><?php echo esc_html( $entry['value'] ); ?></td>
                        <td><?php echo esc_html( $entry['type'] ); ?></td>
                        <td><?php echo esc_html( $entry['value_type'] ); ?></td>
                        <td><?php echo isset( $entry['created'] ) ? esc_html( date( 'Y-m-d H:i:s', $entry['created'] ) ) : 'N/A'; ?></td>
                        <td><?php echo esc_html( $entry['reason'] ?? '' ); ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="delete_<?php echo $list_type; ?>list">
                                <input type="hidden" name="value" value="<?php echo esc_attr( $entry['value'] ); ?>">
                                <input type="hidden" name="type" value="<?php echo esc_attr( $entry['type'] ); ?>">
                                <input type="hidden" name="value_type"
                                       value="<?php echo esc_attr( $entry['value_type'] ); ?>">
                                <button type="submit" class="button button-small">Delete</button>
                            </form>
                        </td>
                    </tr>
				<?php endforeach; ?>
                </tbody>
            </table>
			<?php
		} catch ( Exception $e ) {
			$this->render_error( $e->getMessage() );
		}
	}

	/**
	 * Render URL scan results
	 */
	private function render_url_results( $result ) {
		?>
        <div class="ipqs-grid">
            <!-- URL Status -->
            <div class="ipqs-card">
                <h3>URL Status</h3>
				<?php
				$risk_score   = $result->get_risk_score();
				$status_class = $risk_score < 60 ? 'ipqs-status-safe' :
					( $risk_score < 85 ? 'ipqs-status-warning' : 'ipqs-status-danger' );
				?>
                <span class="ipqs-status <?php echo esc_attr( $status_class ); ?>">
                Risk Score: <?php echo esc_html( $risk_score ); ?>/100
            </span>
            </div>

            <!-- Domain Information -->
            <div class="ipqs-card">
                <h3>Domain Information</h3>
                <p>
					<?php if ( $result->get_domain_age() ): ?>
                        Domain Age: <?php echo esc_html( $result->get_domain_age() ); ?> days<br>
					<?php endif; ?>
					<?php if ( $result->get_domain_rank() ): ?>
                        Domain Rank: <?php echo esc_html( $result->get_domain_rank() ); ?><br>
					<?php endif; ?>
                    DNS Valid: <span
                            class="ipqs-status <?php echo $result->is_dns_valid() ? 'ipqs-status-safe' : 'ipqs-status-danger'; ?>">
                    <?php echo $result->is_dns_valid() ? '✓' : '✗'; ?>
                </span>
                </p>
            </div>

            <!-- Risk Assessment -->
            <div class="ipqs-card">
                <h3>Risk Assessment</h3>
                <ul>
					<?php
					$risk_factors = [
						'suspicious' => 'Suspicious',
						'phishing'   => 'Phishing',
						'malware'    => 'Malware',
						'parking'    => 'Domain Parking',
						'spamming'   => 'Spamming'
					];

					foreach ( $risk_factors as $method => $label ) {
						$is_method = "is_$method";
						if ( method_exists( $result, $is_method ) ) {
							$status       = call_user_func( [ $result, $is_method ] );
							$status_class = $status ? 'ipqs-status-danger' : 'ipqs-status-safe';
							?>
                            <li>
								<?php echo esc_html( $label ); ?>:
                                <span class="ipqs-status <?php echo esc_attr( $status_class ); ?>">
                                <?php echo $status ? '✗' : '✓'; ?>
                            </span>
                            </li>
							<?php
						}
					}
					?>
                </ul>

				<?php if ( $risk_factors = $result->get_risk_factors() ): ?>
                    <h4>Detected Risk Factors:</h4>
                    <ul>
						<?php foreach ( $risk_factors as $factor ): ?>
                            <li><?php echo esc_html( $factor ); ?></li>
						<?php endforeach; ?>
                    </ul>
				<?php endif; ?>
            </div>

            <!-- URL Analysis -->
            <div class="ipqs-card">
                <h3>URL Analysis</h3>
                <p>
					<?php if ( $result->get_content_type() ): ?>
                        Content Type: <?php echo esc_html( $result->get_content_type() ); ?><br>
					<?php endif; ?>
					<?php if ( $result->get_status_code() ): ?>
                        Status Code: <?php echo esc_html( $result->get_status_code() ); ?><br>
					<?php endif; ?>
					<?php if ( $result->get_category() ): ?>
                        Category: <?php echo esc_html( $result->get_category() ); ?>
					<?php endif; ?>
                </p>

				<?php if ( $result->get_redirected_url() ): ?>
                    <h4>Redirect Chain:</h4>
                    <ol>
                        <li>Original URL: <?php echo esc_url( $result->get_redirected_url() ); ?></li>
						<?php if ( $result->get_final_url() ): ?>
                            <li>Final URL: <?php echo esc_url( $result->get_final_url() ); ?></li>
						<?php endif; ?>
                    </ol>
				<?php endif; ?>
            </div>

			<?php if ( $server = $result->get_server_details() ): ?>
                <div class="ipqs-card">
                    <h3>Server Details</h3>
                    <ul>
						<?php foreach ( $server as $key => $value ): ?>
                            <li><?php echo esc_html( ucfirst( $key ) ); ?>: <?php echo esc_html( $value ); ?></li>
						<?php endforeach; ?>
                    </ul>
                </div>
			<?php endif; ?>
        </div>
		<?php
	}

	/**
	 * Handle URL scan
	 */
	private function handle_url_scan() {
		$url = sanitize_url( $_POST['url'] );
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new \Exception( 'Invalid URL format' );
		}

		$options = [];
		if ( isset( $_POST['options'] ) && is_array( $_POST['options'] ) ) {
			foreach ( $_POST['options'] as $option ) {
				$options[ $option ] = true;
			}
		}

		return $this->client->scan_url( $url, $options );
	}

	/**
	 * Render IP test form
	 */
	/**
	 * Render IP test form
	 */
	private function render_ip_test_form() {
		$current_ip = $_SERVER['REMOTE_ADDR'];
		?>
        <form method="post" class="ipqs-card">
            <input type="hidden" name="test_type" value="ip">

            <div class="ipqs-field-group">
                <label for="ip">IP Address:</label>
                <input type="text" name="ip" id="ip" value="<?php echo esc_attr( $current_ip ); ?>"
                       class="regular-text">
                <p class="description">Enter an IP address to analyze (defaults to your current IP)</p>
            </div>

            <div class="ipqs-field-group">
                <label for="strictness">Strictness Level:</label>
                <select name="strictness" id="strictness" class="regular-text">
                    <option value="0">Low (30 Day Reputation Lookback Period — Level 0)</option>
                    <option value="1" selected>Medium (1 Week Reputation Lookback Period — Level 1)</option>
                    <option value="2">High (24 Hour Reputation Lookback Period — Level 2)</option>
                    <option value="3">Highest (12 Hour Reputation Lookback Period — Level 3)</option>
                </select>
                <p class="description">Select the level of strictness for IP reputation analysis</p>
            </div>

            <div class="ipqs-field-group">
                <h4>Analysis Options:</h4>
                <label>
                    <input type="checkbox" name="allow_public_access_points" value="1">
                    Allow Public Access Points (less strict)
                </label>
                <p class="description">Allow IP addresses from corporate, university, and other public ranges while
                    still detecting the worst offenders</p>

                <label>
                    <input type="checkbox" name="mobile_scoring" value="1">
                    Score as Mobile Device (less strict)
                </label>
                <p class="description">Adjusts checks to provide better accuracy for mobile IP addresses</p>

                <label>
                    <input type="checkbox" name="lighter_penalties" value="1">
                    Lighter Penalties for Mixed Quality IPs (less strict)
                </label>
                <p class="description">Lowers detection and elevated Fraud Scores for IP addresses shared by good and
                    bad users</p>
            </div>

			<?php submit_button( 'Analyze IP', 'primary', 'submit' ); ?>
        </form>
		<?php
	}

	/**
	 * Render email test form
	 */
	private function render_email_test_form() {
		?>
        <form method="post" class="ipqs-card">
            <input type="hidden" name="test_type" value="email">

            <div class="ipqs-field-group">
                <label for="email">Email Address:</label>
                <input type="email" name="email" id="email" class="regular-text" required>
                <p class="description">Enter an email address to validate</p>
            </div>

            <div class="ipqs-field-group">
                <h4>Validation Options:</h4>
                <label>
                    <input type="checkbox" name="options[]" value="fast">
                    Fast Validation (Less Accurate)
                </label><br>
                <label>
                    <input type="checkbox" name="options[]" value="suggest_domain">
                    Suggest Domain Corrections
                </label>
            </div>

			<?php submit_button( 'Validate Email', 'primary', 'submit' ); ?>
        </form>
		<?php
	}

	/**
	 * Render phone test form
	 */
	private function render_phone_test_form() {
		?>
        <form method="post" class="ipqs-card">
            <input type="hidden" name="test_type" value="phone">

            <div class="ipqs-field-group">
                <label for="phone">Phone Number:</label>
                <input type="tel" name="phone" id="phone" class="regular-text" required>
                <p class="description">Enter a phone number to validate (e.g., 18007132618)</p>
            </div>

            <div class="ipqs-field-group">
                <label for="country">Country Code:</label>
                <input type="text" name="country" id="country" class="small-text" value="US" maxlength="2">
                <p class="description">Two-letter country code (e.g., US, GB, CA)</p>
            </div>

			<?php submit_button( 'Validate Phone', 'primary', 'submit' ); ?>
        </form>
		<?php
	}

	/**
	 * Render leak check form
	 */
	private function render_leak_check_form() {
		?>
        <form method="post" class="ipqs-card">
            <input type="hidden" name="test_type" value="leak">

            <div class="ipqs-field-group">
                <label for="check_type">Check Type:</label>
                <select name="check_type" id="check_type" class="regular-text" required>
                    <option value="email">Email Address</option>
                    <option value="username">Username</option>
                    <option value="password">Password</option>
                </select>
            </div>

            <div class="ipqs-field-group">
                <label for="check_value">Value to Check:</label>
                <input type="text" name="check_value" id="check_value" class="regular-text" required>
                <p class="description">Enter the value to check for data leaks</p>
            </div>

			<?php submit_button( 'Check for Leaks', 'primary', 'submit' ); ?>
        </form>
		<?php
	}

	/**
	 * Render credit usage info
	 */
	private function render_credit_usage() {
		try {
			$credits = $this->client->get_credit_usage();
			if ( ! is_wp_error( $credits ) ) {
				?>
                <div class="ipqs-card" style="margin-top: 20px;">
                    <h3>API Credit Usage</h3>
                    <table class="widefat striped">
                        <tbody>
                        <tr>
                            <td><strong>Total Credits:</strong></td>
                            <td><?php echo number_format( $credits->get_credits() ); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Total Usage:</strong></td>
                            <td><?php echo number_format( $credits->get_usage() ); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Remaining Credits:</strong></td>
                            <td><?php echo number_format( $credits->get_remaining_credits() ); ?></td>
                        </tr>
                        <tr>
                            <td colspan="2"><h4>Usage by Service</h4></td>
                        </tr>
						<?php if ( $credits->get_proxy_usage() ): ?>
                            <tr>
                                <td>Proxy Detection:</td>
                                <td><?php echo number_format( $credits->get_proxy_usage() ); ?></td>
                            </tr>
						<?php endif; ?>
						<?php if ( $credits->get_email_usage() ): ?>
                            <tr>
                                <td>Email Validation:</td>
                                <td><?php echo number_format( $credits->get_email_usage() ); ?></td>
                            </tr>
						<?php endif; ?>
						<?php if ( $credits->get_phone_usage() ): ?>
                            <tr>
                                <td>Phone Validation:</td>
                                <td><?php echo number_format( $credits->get_phone_usage() ); ?></td>
                            </tr>
						<?php endif; ?>
						<?php if ( $credits->get_url_usage() ): ?>
                            <tr>
                                <td>URL Scanning:</td>
                                <td><?php echo number_format( $credits->get_url_usage() ); ?></td>
                            </tr>
						<?php endif; ?>
						<?php if ( $credits->get_mobile_sdk_usage() ): ?>
                            <tr>
                                <td>Mobile SDK:</td>
                                <td><?php echo number_format( $credits->get_mobile_sdk_usage() ); ?></td>
                            </tr>
						<?php endif; ?>
						<?php if ( $credits->get_fingerprint_usage() ): ?>
                            <tr>
                                <td>Fingerprint:</td>
                                <td><?php echo number_format( $credits->get_fingerprint_usage() ); ?></td>
                            </tr>
						<?php endif; ?>
						<?php if ( $credits->get_usage_percentage() ): ?>
                            <tr>
                                <td><strong>Usage Percentage:</strong></td>
                                <td><?php echo number_format( $credits->get_usage_percentage(), 1 ); ?>%</td>
                            </tr>
						<?php endif; ?>
                        </tbody>
                    </table>
                </div>
				<?php
			}
		} catch ( \Exception $e ) {
			// Silently fail for credit usage display
		}
	}

	/**
	 * Handle form submission and process actions
	 */
	private function handle_form_submission( $test_type ) {
		try {
			$result = null;

			// Handle list management actions and fraud reporting
			if ( isset( $_POST['action'] ) ) {
				switch ( $_POST['action'] ) {
					case 'add_allowlist':
						$result = $this->handle_allowlist_add();
						// Redirect back to lists tab
						wp_redirect( add_query_arg( [ 'page' => 'ipqualityscore-tester', 'tab' => 'lists' ] ) );
						exit;
						break;

					case 'delete_allowlist':
						$result = $this->handle_allowlist_delete();
						// Redirect back to lists tab
						wp_redirect( add_query_arg( [ 'page' => 'ipqualityscore-tester', 'tab' => 'lists' ] ) );
						exit;
						break;

					case 'add_blocklist':
						$result = $this->handle_blocklist_add();
						// Redirect back to lists tab
						wp_redirect( add_query_arg( [ 'page' => 'ipqualityscore-tester', 'tab' => 'lists' ] ) );
						exit;
						break;

					case 'delete_blocklist':
						$result = $this->handle_blocklist_delete();
						// Redirect back to lists tab
						wp_redirect( add_query_arg( [ 'page' => 'ipqualityscore-tester', 'tab' => 'lists' ] ) );
						exit;
						break;

					case 'report_fraud':
						$request_id = sanitize_text_field( $_POST['request_id'] );
						$result     = $this->client->report_request( $request_id );

						if ( ! is_wp_error( $result ) ) {
							add_settings_error(
								'ipqualityscore_messages',
								'report_success',
								'Successfully reported request as fraudulent.',
								'updated'
							);
						} else {
							add_settings_error(
								'ipqualityscore_messages',
								'report_error',
								'Failed to report request: ' . $result->get_error_message(),
								'error'
							);
						}

						// Redirect back to history tab with current request type
						$request_type = $_GET['request_type'] ?? 'proxy';
						wp_redirect( add_query_arg( [
							'page'         => 'ipqualityscore-tester',
							'tab'          => 'history',
							'request_type' => $request_type
						] ) );
						exit;
						break;
				}
			}

			// Handle regular form submissions
			switch ( $test_type ) {
				case 'ip':
					$result = $this->handle_ip_check();
					break;
				case 'email':
					$result = $this->handle_email_check();
					break;
				case 'phone':
					$result = $this->handle_phone_check();
					break;
				case 'leak':
					$result = $this->handle_leak_check();
					break;
				case 'url':
					$result = $this->handle_url_scan();
					break;
			}

			// Only render results for regular form submissions
			if ( $result ) {
				$this->render_results( $result, $test_type );
			}

		} catch ( \Exception $e ) {
			add_settings_error(
				'ipqualityscore_messages',
				'action_error',
				$e->getMessage(),
				'error'
			);

			// On error, redirect back to appropriate tab
			if ( isset( $_POST['action'] ) ) {
				$redirect_tab = strpos( $_POST['action'], 'list' ) !== false ? 'lists' : 'history';
				wp_redirect( add_query_arg( [ 'page' => 'ipqualityscore-tester', 'tab' => $redirect_tab ] ) );
				exit;
			}
		}
	}

	/**
	 * Handle allowlist addition
	 */
	private function handle_allowlist_add() {
		$value      = sanitize_text_field( $_POST['value'] );
		$type       = sanitize_text_field( $_POST['type'] );
		$value_type = sanitize_text_field( $_POST['value_type'] );
		$reason     = ! empty( $_POST['reason'] ) ? sanitize_text_field( $_POST['reason'] ) : null;

		return $this->client->create_allowlist_entry( $value, $type, $value_type, $reason );
	}

	/**
	 * Handle allowlist deletion
	 */
	private function handle_allowlist_delete() {
		$value      = sanitize_text_field( $_POST['value'] );
		$type       = sanitize_text_field( $_POST['type'] );
		$value_type = sanitize_text_field( $_POST['value_type'] );

		return $this->client->delete_allowlist_entry( $value, $type, $value_type );
	}

	/**
	 * Handle blocklist addition
	 */
	private function handle_blocklist_add() {
		$value      = sanitize_text_field( $_POST['value'] );
		$type       = sanitize_text_field( $_POST['type'] );
		$value_type = sanitize_text_field( $_POST['value_type'] );
		$reason     = ! empty( $_POST['reason'] ) ? sanitize_text_field( $_POST['reason'] ) : null;

		return $this->client->create_blocklist_entry( $value, $type, $value_type, $reason );
	}

	/**
	 * Handle blocklist deletion
	 */
	private function handle_blocklist_delete() {
		$value      = sanitize_text_field( $_POST['value'] );
		$type       = sanitize_text_field( $_POST['type'] );
		$value_type = sanitize_text_field( $_POST['value_type'] );

		return $this->client->delete_blocklist_entry( $value, $type, $value_type );
	}

	/**
	 * Handle IP address check
	 */
	private function handle_ip_check() {
		$ip = sanitize_text_field( $_POST['ip'] );
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			throw new \Exception( 'Invalid IP address format' );
		}

		// Set configuration options based on form input
		if ( isset( $_POST['strictness'] ) ) {
			$this->client->set_strictness( (int) $_POST['strictness'] );
		}

		if ( isset( $_POST['allow_public_access_points'] ) ) {
			$this->client->set_allow_public_access_points( true );
		}

		if ( isset( $_POST['lighter_penalties'] ) ) {
			$this->client->set_lighter_penalties( true );
		}

		$options = [];
		// Add mobile scoring if selected
		if ( isset( $_POST['mobile_scoring'] ) ) {
			$options['mobile'] = 1;
		}

		return $this->client->check_ip( $ip, $options );
	}

	/**
	 * Handle email validation
	 */
	private function handle_email_check() {
		$email = sanitize_email( $_POST['email'] );
		if ( ! is_email( $email ) ) {
			throw new \Exception( 'Invalid email address format' );
		}

		$options = $this->get_options_from_post();

		return $this->client->validate_email( $email, $options );
	}

	/**
	 * Handle phone validation
	 */
	private function handle_phone_check() {
		$phone   = sanitize_text_field( $_POST['phone'] );
		$country = sanitize_text_field( $_POST['country'] );

		if ( empty( $phone ) ) {
			throw new \Exception( 'Phone number is required' );
		}

		$options = [];
		if ( ! empty( $country ) ) {
			$options['country'] = $country;
		}

		return $this->client->validate_phone( $phone, $options );
	}

	/**
	 * Handle leak check
	 */
	private function handle_leak_check() {
		$type  = sanitize_text_field( $_POST['check_type'] );
		$value = sanitize_text_field( $_POST['check_value'] );

		if ( ! in_array( $type, [ 'email', 'username', 'password' ] ) ) {
			throw new \Exception( 'Invalid check type' );
		}

		if ( empty( $value ) ) {
			throw new \Exception( 'Value to check is required' );
		}

		return $this->client->check_leaked_data( $value, $type );
	}

	/**
	 * Get options from POST data
	 */
	private function get_options_from_post() {
		$options = [];
		if ( isset( $_POST['options'] ) && is_array( $_POST['options'] ) ) {
			foreach ( $_POST['options'] as $option ) {
				$options[ sanitize_key( $option ) ] = true;
			}
		}

		return $options;
	}

	/**
	 * Render API response results
	 */
	private function render_results( $result, $test_type ) {
		if ( is_wp_error( $result ) ) {
			$this->render_error( $result->get_error_message() );

			return;
		}

		?>
        <div class="ipqs-results">
            <h2>Results</h2>
			<?php
			switch ( $test_type ) {
				case 'ip':
					$this->render_ip_results( $result );
					break;
				case 'email':
					$this->render_email_results( $result );
					break;
				case 'phone':
					$this->render_phone_results( $result );
					break;
				case 'leak':
					$this->render_leak_results( $result );
					break;
			}

			if ( WP_DEBUG ): ?>
                <div class="ipqs-card">
                    <h3>Raw Response Data</h3>
                    <pre><?php print_r( $result->get_all() ); ?></pre>
                </div>
			<?php endif; ?>
        </div>
		<?php
	}

	/**
	 * Render IP check results
	 */
	private function render_ip_results( $result ) {
		?>
        <div class="ipqs-grid">
            <!-- Fraud Score -->
            <div class="ipqs-card">
                <h3>Fraud Score</h3>
				<?php
				$fraud_score  = $result->get_fraud_score();
				$status_class = 'ipqs-status-safe';
				if ( $fraud_score >= 90 ) {
					$status_class = 'ipqs-status-danger';
				} elseif ( $fraud_score >= 75 ) {
					$status_class = 'ipqs-status-warning';
				}
				?>
                <span class="ipqs-status <?php echo esc_attr( $status_class ); ?>">
                    <?php echo esc_html( $fraud_score ); ?>/100
                </span>
                <p>Risk Level: <?php echo esc_html( $result->get_risk_level() ); ?></p>
            </div>

            <!-- Location Information -->
            <div class="ipqs-card">
                <h3>Location</h3>
                <p>
					<?php if ( $result->get_city() ): ?>
                        City: <?php echo esc_html( $result->get_city() ); ?><br>
					<?php endif; ?>
					<?php if ( $result->get_region() ): ?>
                        Region: <?php echo esc_html( $result->get_region() ); ?><br>
					<?php endif; ?>
					<?php if ( $result->get_country_code() ): ?>
                        Country: <?php echo esc_html( $result->get_country_code() ); ?><br>
					<?php endif; ?>
					<?php if ( $result->get_timezone() ): ?>
                        Timezone: <?php echo esc_html( $result->get_timezone() ); ?>
					<?php endif; ?>
                </p>
            </div>

            <!-- Connection Details -->
            <div class="ipqs-card">
                <h3>Connection Details</h3>
                <p>
					<?php if ( $result->get_isp() ): ?>
                        ISP: <?php echo esc_html( $result->get_isp() ); ?><br>
					<?php endif; ?>
					<?php if ( $result->get_organization() ): ?>
                        Organization: <?php echo esc_html( $result->get_organization() ); ?><br>
					<?php endif; ?>
					<?php if ( $result->get_asn() ): ?>
                        ASN: <?php echo esc_html( $result->get_asn() ); ?><br>
					<?php endif; ?>
                    Connection Type: <?php echo esc_html( $result->get_connection_type() ?? 'Unknown' ); ?>
                </p>
            </div>

            <!-- Risk Factors -->
            <div class="ipqs-card">
                <h3>Risk Factors</h3>
                <ul>
					<?php
					// Risk factor checks
					$risk_factors = [
						'proxy'            => 'Proxy/VPN',
						'vpn'              => 'VPN',
						'tor'              => 'TOR',
						'crawler'          => 'Crawler',
						'bot'              => 'Bot',
						'active_vpn'       => 'Active VPN',
						'active_tor'       => 'Active TOR',
						'frequent_abuser'  => 'Frequent Abuser',
						'security_scanner' => 'Security Scanner',
						'mobile'           => 'Mobile Device'
					];

					foreach ( $risk_factors as $method => $label ) {
						$method_name = str_starts_with( $method, 'active_' ) ? 'is_' . $method :
							( in_array( $method, [ 'crawler', 'mobile' ] ) ? 'is_' . $method :
								( str_starts_with( $method, 'has_' ) ? $method : 'is_' . $method ) );

						if ( method_exists( $result, $method_name ) ) {
							$status       = call_user_func( [ $result, $method_name ] );
							$status_class = $status ? 'ipqs-status-danger' : 'ipqs-status-safe';
							?>
                            <li>
								<?php echo esc_html( $label ); ?>:
                                <span class="ipqs-status <?php echo esc_attr( $status_class ); ?>">
                        <?php echo $status ? '✓' : '✗'; // Inverted the check/x mark ?>
                    </span>
                            </li>
							<?php
						}
					}

					// Additional checks with has_ prefix
					$additional_checks = [
						'recent_abuse'      => 'Recent Abuse',
						'high_risk_attacks' => 'High Risk Attacks'
					];

					foreach ( $additional_checks as $method => $label ) {
						$method_name = 'has_' . $method;
						if ( method_exists( $result, $method_name ) ) {
							$status       = call_user_func( [ $result, $method_name ] );
							$status_class = $status ? 'ipqs-status-danger' : 'ipqs-status-safe';
							?>
                            <li>
								<?php echo esc_html( $label ); ?>:
                                <span class="ipqs-status <?php echo esc_attr( $status_class ); ?>">
                        <?php echo $status ? '✓' : '✗'; // Inverted the check/x mark ?>
                    </span>
                            </li>
							<?php
						}
					}
					?>
                </ul>
            </div>
        </div>
		<?php
	}

	/**
	 * Render email validation results
	 */
	/**
	 * Render email validation results
	 */
	private function render_email_results( $result ) {
		?>
        <div class="ipqs-grid">
            <!-- Validation Status -->
            <div class="ipqs-card">
                <h3>Validation Status</h3>
				<?php
				$status_class = $result->is_valid() ? 'ipqs-status-safe' : 'ipqs-status-danger';
				?>
                <span class="ipqs-status <?php echo esc_attr( $status_class ); ?>">
                <?php echo $result->is_valid() ? 'Valid' : 'Invalid'; ?>
            </span>
				<?php if ( $result->get_sanitized_email() ): ?>
                    <p>Sanitized Email: <?php echo esc_html( $result->get_sanitized_email() ); ?></p>
				<?php endif; ?>
            </div>

            <!-- Risk Assessment -->
            <div class="ipqs-card">
                <h3>Risk Assessment</h3>
                <ul>
					<?php
					// Score-based checks
					$risk_scores = array(
						'smtp_score'    => 'SMTP Score',
						'overall_score' => 'Overall Score',
						'fraud_score'   => 'Fraud Score',
					);

					foreach ( $risk_scores as $method => $label ) {
						$score = call_user_func( [ $result, "get_$method" ] );
						if ( $score !== null ) {
							$status_class = $score < 60 ? 'ipqs-status-safe' :
								( $score < 80 ? 'ipqs-status-warning' : 'ipqs-status-danger' );
							?>
                            <li>
								<?php echo esc_html( $label ); ?>:
                                <span class="ipqs-status <?php echo esc_attr( $status_class ); ?>">
                                <?php echo esc_html( $score ); ?>
                            </span>
                            </li>
							<?php
						}
					}

					// Risk factor checks
					$risk_factors = [
						'disposable'          => 'Disposable Email',
						'honeypot'            => 'Honeypot',
						'catch_all'           => 'Catch-all Domain',
						'generic'             => 'Generic Email',
						'common'              => 'Common Email Pattern',
						'suspect'             => 'Suspicious',
						'recent_abuse'        => 'Recent Abuse',
						'leaked'              => 'Found in Data Leaks',
						'frequent_complainer' => 'Frequent Complainer'
					];

					foreach ( $risk_factors as $method => $label ) {
						$is_method = "is_$method";
						if ( method_exists( $result, $is_method ) ) {
							$status = call_user_func( [ $result, $is_method ] );
							if ( $status !== null ) {
								$status_class = $status ? 'ipqs-status-danger' : 'ipqs-status-safe';
								?>
                                <li>
									<?php echo esc_html( $label ); ?>:
                                    <span class="ipqs-status <?php echo esc_attr( $status_class ); ?>">
                                    <?php echo $status ? '✗' : '✓'; ?>
                                </span>
                                </li>
								<?php
							}
						}
					}
					?>
                </ul>
            </div>

            <!-- Domain Information -->
            <div class="ipqs-card">
                <h3>Domain Information</h3>
                <p>
					<?php if ( $first_seen = $result->get_first_seen() ): ?>
                        First Seen: <?php echo esc_html( $first_seen['human'] ?? 'Unknown' ); ?><br>
					<?php endif; ?>

					<?php if ( $domain_age = $result->get_domain_age() ): ?>
                        Domain Age: <?php echo esc_html( $domain_age['human'] ?? 'Unknown' ); ?><br>
					<?php endif; ?>

                    DNS Valid: <span
                            class="ipqs-status <?php echo $result->is_dns_valid() ? 'ipqs-status-safe' : 'ipqs-status-danger'; ?>">
                    <?php echo $result->is_dns_valid() ? '✓' : '✗'; ?>
                </span>

					<?php if ( $result->has_spf_record() || $result->has_dmarc_record() ): ?>
                        <br>Email Security:
						<?php if ( $result->has_spf_record() ): ?>
                            SPF ✓
						<?php endif; ?>
						<?php if ( $result->has_dmarc_record() ): ?>
                            DMARC ✓
						<?php endif; ?>
					<?php endif; ?>
                </p>

				<?php if ( ! empty( $result->get_mx_records() ) ): ?>
                    <div class="ipqs-subcard">
                        <h4>MX Records</h4>
                        <ul>
							<?php foreach ( $result->get_mx_records() as $record ): ?>
                                <li><?php echo esc_html( $record ); ?></li>
							<?php endforeach; ?>
                        </ul>
                    </div>
				<?php endif; ?>
            </div>

            <!-- Domain Analysis -->
			<?php if ( $result->get_domain_velocity() || $result->get_domain_trust() || $result->get_user_activity() ): ?>
                <div class="ipqs-card">
                    <h3>Domain Analysis</h3>
                    <p>
						<?php if ( $velocity = $result->get_domain_velocity() ): ?>
                            Domain Velocity: <?php echo esc_html( $velocity ); ?><br>
						<?php endif; ?>
						<?php if ( $trust = $result->get_domain_trust() ): ?>
                            Domain Trust: <?php echo esc_html( $trust ); ?><br>
						<?php endif; ?>
						<?php if ( $activity = $result->get_user_activity() ): ?>
                            User Activity: <?php echo esc_html( $activity ); ?>
						<?php endif; ?>
                    </p>
                </div>
			<?php endif; ?>

            <!-- Associated Data -->
			<?php
			$associated_names  = $result->get_associated_names();
			$associated_phones = $result->get_associated_phone_numbers();

			if ( ! empty( $associated_names ) || ! empty( $associated_phones ) ):
				?>
                <div class="ipqs-card">
                    <h3>Associated Data</h3>
					<?php if ( ! empty( $associated_names ) ): ?>
                        <h4>Names:</h4>
						<?php if ( is_array( $associated_names ) && ! isset( $associated_names[0] ) ): ?>
                            <p class="description">Enterprise Plus or higher required.</p>
						<?php else: ?>
                            <ul>
								<?php foreach ( $associated_names as $name ): ?>
                                    <li><?php echo esc_html( $name ); ?></li>
								<?php endforeach; ?>
                            </ul>
						<?php endif; ?>
					<?php endif; ?>

					<?php if ( ! empty( $associated_phones ) ): ?>
                        <h4>Phone Numbers:</h4>
						<?php if ( is_array( $associated_phones ) && ! isset( $associated_phones[0] ) ): ?>
                            <p class="description">Enterprise Plus or higher required.</p>
						<?php else: ?>
                            <ul>
								<?php foreach ( $associated_phones as $phone ): ?>
                                    <li><?php echo esc_html( $phone ); ?></li>
								<?php endforeach; ?>
                            </ul>
						<?php endif; ?>
					<?php endif; ?>
                </div>
			<?php endif; ?>

            <!-- Domain Suggestion -->
			<?php if ( $result->get_suggested_domain() ): ?>
                <div class="ipqs-card">
                    <h3>Suggested Correction</h3>
                    <p>Did you mean a domain correction to: <?php echo esc_html( $result->get_suggested_domain() ); ?>
                        ?</p>
                </div>
			<?php endif; ?>
        </div>
		<?php
	}

	/**
	 * Render phone validation results
	 */
	private function render_phone_results( $result ) {
		?>
        <div class="ipqs-grid">
            <!-- Validation Status -->
            <div class="ipqs-card">
                <h3>Phone Status</h3>
				<?php
				$status_class = $result->is_valid() ? 'ipqs-status-safe' : 'ipqs-status-danger';
				?>
                <span class="ipqs-status <?php echo esc_attr( $status_class ); ?>">
                <?php echo $result->is_valid() ? 'Valid' : 'Invalid'; ?>
            </span>

				<?php if ( $result->get_formatted() ): ?>
                    <p>Formatted Number: <?php echo esc_html( $result->get_formatted() ); ?></p>
				<?php endif; ?>
            </div>

            <!-- Location Info -->
            <div class="ipqs-card">
                <h3>Location</h3>
                <p>
					<?php if ( $result->get_carrier() ): ?>
                        Carrier: <?php echo esc_html( $result->get_carrier() ); ?><br>
					<?php endif; ?>
					<?php if ( $result->get_line_type() ): ?>
                        Line Type: <?php echo esc_html( $result->get_line_type() ); ?><br>
					<?php endif; ?>
					<?php if ( $result->get_country() ): ?>
                        Country: <?php echo esc_html( $result->get_country() ); ?>
					<?php endif; ?>
                </p>
            </div>

            <!-- Risk Assessment -->
            <div class="ipqs-card">
                <h3>Risk Assessment</h3>
                <ul>
					<?php
					$risk_factors = [
						'active'      => [ 'method' => 'is_active', 'label' => 'Active Number' ],
						'risky'       => [ 'method' => 'is_risky', 'label' => 'Risky Number' ],
						'do_not_call' => [ 'method' => 'is_do_not_call', 'label' => 'Do Not Call Listed' ],
						'spammer'     => [ 'method' => 'is_spammer', 'label' => 'Associated with Spam' ],
					];

					foreach ( $risk_factors as $factor ) {
						if ( method_exists( $result, $factor['method'] ) ) {
							$status = call_user_func( [ $result, $factor['method'] ] );
							if ( $status !== null ) {
								$status_class = $factor['method'] === 'is_active' ?
									( $status ? 'ipqs-status-safe' : 'ipqs-status-danger' ) :
									( $status ? 'ipqs-status-danger' : 'ipqs-status-safe' );
								?>
                                <li>
									<?php echo esc_html( $factor['label'] ); ?>:
                                    <span class="ipqs-status <?php echo esc_attr( $status_class ); ?>">
                                    <?php echo $status ? '✓' : '✗'; ?>
                                </span>
                                </li>
								<?php
							}
						}
					}
					?>
                </ul>
            </div>
        </div>
		<?php
	}

	/**
	 * Render leak check results
	 */
	/**
	 * Render leak check results
	 */
	private function render_leak_results( $result ) {
		?>
        <div class="ipqs-grid">
            <!-- Leak Status -->
            <div class="ipqs-card">
                <h3>Data Leak Status</h3>
				<?php
				$is_exposed   = $result->is_exposed();
				$status_class = $is_exposed ? 'ipqs-status-danger' : 'ipqs-status-safe';
				?>
                <span class="ipqs-status <?php echo esc_attr( $status_class ); ?>">
                <?php echo $is_exposed ? 'Found in Data Leaks' : 'No Leaks Found'; ?>
            </span>

				<?php if ( $first_seen = $result->get_first_seen() ): ?>
                    <p>Check Time: <?php echo esc_html( $first_seen['human'] ); ?></p>
				<?php endif; ?>

				<?php if ( $message = $result->get_message() ): ?>
                    <p><?php echo esc_html( $message ); ?></p>
				<?php endif; ?>
            </div>

			<?php if ( $is_exposed ): ?>
                <!-- Leak Details -->
                <div class="ipqs-card">
                    <h3>Leak Details</h3>
					<?php if ( $first_seen = $result->get_first_seen() ): ?>
                        <p>First Seen: <?php echo esc_html( $first_seen['human'] ); ?></p>
					<?php endif; ?>

					<?php
					$sources = $result->get_sources();
					if ( ! empty( $sources ) ):
						if ( is_array( $sources ) && isset( $sources[0] ) && $sources[0] === 'You must be Enterprise or higher to view the leak sources' ): ?>
                            <p class="description"><?php echo esc_html( $sources[0] ); ?></p>
						<?php else: ?>
                            <h4>Found in Sources:</h4>
                            <ul>
								<?php foreach ( $sources as $source ): ?>
                                    <li><?php echo esc_html( $source ); ?></li>
								<?php endforeach; ?>
                            </ul>
						<?php endif;
					endif; ?>
                </div>
			<?php endif; ?>

			<?php if ( WP_DEBUG ): ?>
                <div class="ipqs-card">
                    <h3>Raw Response</h3>
                    <pre><?php print_r( $result->get_all() ); ?></pre>
                </div>
			<?php endif; ?>
        </div>
		<?php
	}

	/**
	 * Render error message
	 */
	private function render_error( $message ) {
		?>
        <div class="notice notice-error">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
		<?php
	}

	/**
	 * Enhance JavaScript for tab functionality and dynamic form updates
	 */
	private function render_js() {
		?>
        <script>
            jQuery(document).ready(function ($) {
                // Tab functionality
                $('.nav-tab').click(function (e) {
                    e.preventDefault();
                    var tab = $(this).data('tab');

                    // Update active tab
                    $('.nav-tab').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');

                    // Show active content
                    $('.ipqs-tab-content').removeClass('active');
                    $('#tab-' + tab).addClass('active');

                    // Update hidden input
                    $('input[name="test_type"]').val(tab);

                    // If this is the history tab, preserve request type from select
                    if (tab === 'history') {
                        var requestType = $('#request_type').val() || 'proxy';
                        var currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('page', 'ipqualityscore-tester');
                        currentUrl.searchParams.set('tab', tab);
                        currentUrl.searchParams.set('request_type', requestType);
                        window.history.pushState({}, '', currentUrl.toString());
                    }
                });

                // Allow/Block list tab functionality
                $('.ipqs-list-content:not(.active)').hide();
                $('.nav-tab-wrapper .nav-tab').click(function (e) {
                    e.preventDefault();
                    var list = $(this).data('list');

                    $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');

                    $('.ipqs-list-content').hide();
                    $('#' + list + '-list').show();
                });

                // Dynamic form field updates based on type selection
                function updateValueTypeOptions(typeSelect, valueTypeSelect) {
                    var type = $(typeSelect).val();
                    var $valueType = $(valueTypeSelect);

                    $valueType.empty();

                    switch (type) {
                        case 'proxy':
                            $valueType.append(
                                '<option value="ip">IP Address</option>' +
                                '<option value="cidr">CIDR Range</option>' +
                                '<option value="isp">ISP</option>'
                            );
                            break;
                        case 'url':
                            $valueType.append('<option value="domain">Domain</option>');
                            break;
                        case 'email':
                            $valueType.append('<option value="email">Email Address</option>');
                            break;
                        case 'phone':
                            $valueType.append('<option value="phone">Phone Number</option>');
                            break;
                    }
                }

                // Bind change events for type selects
                $('#allow_type, #block_type').change(function () {
                    var prefix = $(this).attr('id').split('_')[0];
                    updateValueTypeOptions(this, '#' + prefix + '_value_type');
                });

                // Initialize value type options
                updateValueTypeOptions('#allow_type', '#allow_value_type');
                updateValueTypeOptions('#block_type', '#block_value_type');

                // Report fraud functionality
                window.reportRequest = function (requestId) {
                    if (confirm('Are you sure you want to report this request as fraudulent?')) {
                        $.post(ajaxurl, {
                            action: 'ipqs_report_fraud',
                            request_id: requestId,
                            nonce: '<?php echo wp_create_nonce( 'ipqs_report_fraud' ); ?>'
                        }, function (response) {
                            alert(response.success ? 'Successfully reported as fraud' : 'Failed to report fraud');
                        });
                    }
                };
            });
        </script>
		<?php
	}

}

// Initialize the plugin
new Plugin();