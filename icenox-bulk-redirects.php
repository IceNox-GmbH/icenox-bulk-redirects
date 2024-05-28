<?php
/**
 * Plugin Name:         IceNox Bulk Redirects
 * Description:         Redirect multiple pages to a single page.
 * Version:             2.1
 * Requires at least:   6.2
 * Requires PHP:        8.0
 * Author:              IceNox GmbH
 * Author URI:          https://icenox.com
 * Licence:             GPLv3
 * License URI:         https://www.gnu.org/licenses/gpl-3.0
 * Text Domain:         icenox-bulk-redirects
 */

class IceNoxBulkRedirects {
	private bool $enabled;
	private int $status_code;
	private string $redirect_url;
	private array $path_list;

	private array $status_code_options = [
		301 => "Moved Permanently",
		302 => "Moved Temporarily",
		307 => "Temporary Redirect",
		308 => "Permanent Redirect",
	];

	public function __construct() {
		$this->enabled      = get_option( 'icenox_bulk_redirects_enabled' ) === "on";
		$this->status_code  = get_option( 'icenox_bulk_redirects_status_code' ) ?: 302;
		$this->redirect_url = get_option( 'icenox_bulk_redirects_url' );
		$this->path_list    = json_decode( get_option( 'icenox_bulk_redirects_path_list' ), true ) ?: [];

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', function () {
				wp_register_style( 'icenox-bulk-redirects-admin-styles', plugins_url( 'admin.css', __FILE__ ) );
				wp_register_script( 'icenox-bulk-redirects-admin-scripts', plugins_url( 'admin.js', __FILE__ ) );
			} );

			add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
			add_action( 'admin_init', [ $this, 'settings_page_init' ] );

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [
				$this,
				'icenox_bulk_redirects_settings_link'
			] );

		}

		if ( $this->enabled ) {
			$this->handle_redirects();
		}
	}

	public function icenox_bulk_redirects_settings_link( array $links ): array {
		$url           = get_admin_url() . "admin.php?page=icenox-bulk-redirects";
		$settings_link = '<a href="' . $url . '">' . __( 'Settings' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	private function handle_redirects(): void {
		if ( empty( $this->redirect_url ) || empty( $this->path_list ) ) {
			return;
		}

		$currentPath = parse_url( $_SERVER["REQUEST_URI"], PHP_URL_PATH );
		if ( in_array( $currentPath, $this->path_list ) || in_array( $currentPath . "/", $this->path_list ) ) {
			header( "Location: " . $this->redirect_url, true, $this->status_code );
			exit();
		}
	}

	public function add_settings_page(): void {
		add_menu_page(
			'Bulk Redirects',
			'Bulk Redirects',
			'manage_options',
			'icenox-bulk-redirects',
			[ $this, 'create_settings_page' ],
			'dashicons-admin-links'
		);
	}

	public function create_settings_page(): void {
		wp_enqueue_style( 'icenox-bulk-redirects-admin-styles' );
		wp_enqueue_script( 'icenox-bulk-redirects-admin-scripts' );
		?>

        <div class="wrap icenox-bulk-redirects">
            <h2>Bulk Redirects</h2>
            <p></p>
			<?php settings_errors(); ?>
            <table class="added-paths">
                <thead>
                <tr>
                    <td class="path">Path</td>
                    <td class="remove-row">Remove</td>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( $this->path_list as $key => $path ): ?>
                    <tr>
                        <td class="path-row"><?php echo $path; ?></td>
                        <td class="remove-row">
                            <button class="remove-button button-secondary" data-path-key="<?php echo $key; ?>"
                                    data-wpnonce="<?php echo wp_create_nonce( "icenox_redirects_option_group-options" ); ?>">
                                Remove
                            </button>
                        </td>
                    </tr>
				<?php endforeach;
				if ( empty( $this->path_list ) ):?>
                    <tr>
                        <td class="path-row">Please first add a new Path below.</td>
                    </tr>
				<?php endif; ?>
                </tbody>
            </table>
            <form class="bulk-redirects-form" method="post" action="options.php">
				<?php
				settings_fields( 'icenox_redirects_option_group' );
				do_settings_sections( 'icenox-redirects-admin' );
				submit_button();
				?>
            </form>
        </div>
		<?php
	}

	public function settings_page_init(): void {
		add_settings_section(
			'icenox_redirects_settings_section',
			'Settings',
			'',
			'icenox-redirects-admin',
			[
				'before_section' => '<section class="%s">',
				'after_section'  => '</section>',
				'section_class'  => 'settings-section',
			]
		);

		register_setting(
			'icenox_redirects_option_group',
			'icenox_bulk_redirects_enabled',
			[
				'type'              => 'string',
				'sanitize_callback' => function ( $value ) {
					return $value ?? ( $this->enabled ? "on" : "off" );
				},
				'default'           => 'no'
			]
		);

		add_settings_field(
			"enabled-status",
			"Enabled",
			[ $this, 'enabled_status_checkbox' ],
			'icenox-redirects-admin',
			'icenox_redirects_settings_section'
		);

		register_setting(
			'icenox_redirects_option_group',
			'icenox_bulk_redirects_status_code',
			[
				'type'              => 'int',
				'sanitize_callback' => [ $this, "status_code_callback" ],
				'default'           => 302
			]
		);

		add_settings_field(
			"status-code",
			"Status Code",
			[ $this, 'status_code_selection' ],
			'icenox-redirects-admin',
			'icenox_redirects_settings_section'
		);

		register_setting(
			'icenox_redirects_option_group',
			'icenox_bulk_redirects_url',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, "redirect_url_callback" ],
				'default'           => ''
			]
		);

		add_settings_field(
			"redirect-url",
			"Redirect URL",
			[ $this, 'redirect_url_input' ],
			'icenox-redirects-admin',
			'icenox_redirects_settings_section'
		);

		register_setting(
			'icenox_redirects_option_group',
			'icenox_bulk_redirects_path_list',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, "add_new_path_callback" ],
				'default'           => []
			]
		);

		add_settings_field(
			"add-new-path",
			"Add new Path",
			[ $this, 'add_new_path_input' ],
			'icenox-redirects-admin',
			'icenox_redirects_settings_section'
		);

		register_setting(
			'icenox_redirects_option_group',
			'icenox_bulk_redirects_remove_path',
			[
				'type'              => 'int',
				'sanitize_callback' => [ $this, "remove_path_callback" ],
				'default'           => null
			]
		);
	}

	public function enabled_status_checkbox(): void {
		$enabled = $this->enabled;
		?>
        <label class="input-label sr-only" for="icenox-bulk-redirects-enabled">Enabled</label>
		<?php if ( empty( $this->redirect_url ) || empty( $this->path_list ) ): ?>
            <input id="icenox-bulk-redirects-enabled" name="icenox_bulk_redirects_enabled" type="hidden" value="off">
            <div class="disabled-note">Can be enabled once the Redirect URL and at least one Path has been added.</div>
		<?php else: ?>
            <input id="icenox-bulk-redirects-enabled" name="icenox_bulk_redirects_enabled"
                   type="checkbox" value="on" <?php echo $enabled ? "checked" : ""; ?>>
		<?php endif;
	}

	public function status_code_selection(): void {
		?>
        <label class="input-label sr-only" for="icenox-bulk-redirects-status-code">Status Code</label>
        <select id="icenox-bulk-redirects-status-code" name="icenox_bulk_redirects_status_code">
			<?php
			foreach ( $this->status_code_options as $status_code => $description ) {
				if ( $this->status_code === $status_code ) {
					echo '<option value="' . $status_code . '" selected>' . $status_code . ' (' . $description . ')</option>';
				} else {
					echo '<option value="' . $status_code . '">' . $status_code . ' (' . $description . ')</option>';
				}
			}
			?>
        </select>
		<?php
	}

	public function redirect_url_input(): void {
		$redirectUrl = $this->redirect_url;
		?>
        <label class="input-label sr-only" for="icenox-bulk-redirects-url">URL</label>
        <input id="icenox-bulk-redirects-url" name="icenox_bulk_redirects_url" type="url"
               value="<?php echo $redirectUrl; ?>">
		<?php
	}

	public function add_new_path_input(): void {
		?>
        <label class="input-label sr-only" for="icenox-bulk-redirects-add-path">Path</label>
        <input id="icenox-bulk-redirects-add-path" name="icenox_bulk_redirects_path_list" type="text"
               placeholder="/new-path/" value="">
        <div class="add-path-description">
            Please note, the Path is matched exactly.<br>The path needs to start with a slash (/) and a trailing slash
            is often required too. Don't add any query strings or fragments.
        </div>
		<?php
	}

	public function redirect_url_callback( $value ): string {
		if ( isset( $value ) ) {
			return filter_var( $value, FILTER_VALIDATE_URL ) ? $value : "";
		} else {
			return $this->redirect_url;
		}
	}

	public function status_code_callback( $value ): int {
		if ( isset( $value ) ) {
			return in_array( $value, array_keys( $this->status_code_options ) ) ? $value : 302;
		} else {
			return $this->status_code;
		}
	}

	public function add_new_path_callback( $value ): string {
		$pathList = $this->path_list;

		if ( is_string( $value ) && str_starts_with( $value, "/" ) ) {
			$pathList[] = $value;
		}

		if ( isset( $_POST["icenox_bulk_redirects_remove_path"] ) ) {
			$removeIndex = $_POST["icenox_bulk_redirects_remove_path"];
			if ( is_numeric( $removeIndex ) && isset( $pathList[ $removeIndex ] ) ) {
				unset( $pathList[ $removeIndex ] );
			}
		}

		return json_encode( (object) $pathList );
	}

	public function remove_path_callback( $value ): string {
		$pathList = $this->path_list;

		if ( is_numeric( $value ) && isset( $pathList[ $value ] ) ) {
			unset( $pathList[ $value ] );
			update_option( "icenox_bulk_redirects_path_list", $pathList );
			$this->path_list = $pathList;
		}

		return "";
	}

}

$icenox_bulk_redirects = new IceNoxBulkRedirects();
