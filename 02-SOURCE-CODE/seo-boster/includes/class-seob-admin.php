<?php
/**
 * Admin menu, dashboard and shared layout helpers.
 *
 * @package SEO_Boster
 */

defined( 'ABSPATH' ) || exit;

final class SEOB_Admin {

	/** @var SEOB_Plugin */
	private $plugin;

	public function __construct( SEOB_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_seob_toggle_module', array( $this, 'toggle_module' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SEOB_FILE ), array( $this, 'action_links' ) );
	}

	public function menu() {
		$cap = SEOB_Security::cap();
		add_menu_page( 'SEO Boster', 'SEO Boster', $cap, 'seo-boster', array( $this, 'dashboard' ), 'dashicons-chart-line', 58 );
		add_submenu_page( 'seo-boster', __( 'Dashboard', 'seo-boster' ), __( 'Dashboard', 'seo-boster' ), $cap, 'seo-boster', array( $this, 'dashboard' ) );

		foreach ( $this->plugin->enabled_modules() as $module ) {
			add_submenu_page( 'seo-boster', $module->name(), $module->name(), $cap, $module->menu_slug(), array( $module, 'render_page' ) );
		}
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'seo-boster' ) ) {
			return;
		}
		wp_enqueue_style( 'seob-admin', SEOB_URL . 'assets/css/admin.css', array(), SEOB_VERSION );
		wp_enqueue_script( 'seob-admin', SEOB_URL . 'assets/js/admin.js', array(), SEOB_VERSION, true );
		wp_localize_script(
			'seob-admin',
			'seobAdmin',
			array(
				'copied'  => __( 'Copied', 'seo-boster' ),
				'confirm' => __( 'Are you sure?', 'seo-boster' ),
			)
		);
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=seo-boster' ) ) . '">' . esc_html__( 'Open', 'seo-boster' ) . '</a>' );
		return $links;
	}

	/**
	 * Turn a module on or off from the dashboard.
	 */
	public function toggle_module() {
		SEOB_Security::verify( 'seob_toggle_module' );

		$id      = sanitize_key( SEOB_Security::post_text( 'module' ) );
		$modules = $this->plugin->modules();
		if ( ! isset( $modules[ $id ] ) ) {
			SEOB_Security::redirect( admin_url( 'admin.php?page=seo-boster' ) );
		}

		$disabled = (array) get_option( 'seob_disabled_modules', array() );
		if ( in_array( $id, $disabled, true ) ) {
			$disabled = array_values( array_diff( $disabled, array( $id ) ) );
		} else {
			$disabled[] = $id;
		}
		update_option( 'seob_disabled_modules', array_values( array_unique( $disabled ) ), false );

		SEOB_Security::redirect( add_query_arg( 'seob_msg', 'module_toggled', admin_url( 'admin.php?page=seo-boster' ) ) );
	}

	/**
	 * Dashboard screen with one card per module.
	 */
	public function dashboard() {
		if ( ! SEOB_Security::can_use() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'seo-boster' ), 403 );
		}

		self::page_start( 'SEO Boster', __( 'Your SEO toolkit. Each tool below is a module, and new ones can be added any time.', 'seo-boster' ) );
		self::notice_from_query( array( 'module_toggled' => array( 'success', __( 'Module setting saved.', 'seo-boster' ) ) ) );

		$stats = SEOB_DB::stats();
		?>
		<div class="seob-stats">
			<div class="seob-stat"><span class="seob-stat-num"><?php echo esc_html( number_format_i18n( $stats['keywords'] ) ); ?></span><span class="seob-stat-label"><?php esc_html_e( 'Keywords live on the site', 'seo-boster' ); ?></span></div>
			<div class="seob-stat"><span class="seob-stat-num"><?php echo esc_html( number_format_i18n( $stats['pages'] ) ); ?></span><span class="seob-stat-label"><?php esc_html_e( 'Pages boosted', 'seo-boster' ); ?></span></div>
			<div class="seob-stat"><span class="seob-stat-num"><?php echo esc_html( number_format_i18n( count( $this->plugin->enabled_modules() ) ) ); ?></span><span class="seob-stat-label"><?php esc_html_e( 'Active modules', 'seo-boster' ); ?></span></div>
		</div>

		<h2 class="seob-section-title"><?php esc_html_e( 'Modules', 'seo-boster' ); ?></h2>
		<div class="seob-modules">
			<?php foreach ( $this->plugin->modules() as $id => $module ) : ?>
				<?php $on = $this->plugin->is_enabled( $id ); ?>
				<div class="seob-module-card <?php echo $on ? 'is-on' : 'is-off'; ?>">
					<div class="seob-module-icon"><span class="dashicons <?php echo esc_attr( $module->icon() ); ?>"></span></div>
					<div class="seob-module-body">
						<h3><?php echo esc_html( $module->name() ); ?></h3>
						<p><?php echo esc_html( $module->description() ); ?></p>
						<?php if ( $on ) : ?>
							<ul class="seob-module-stats">
								<?php foreach ( $module->stats() as $label => $value ) : ?>
									<li><strong><?php echo esc_html( (string) $value ); ?></strong> <?php echo esc_html( $label ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
					<div class="seob-module-actions">
						<?php if ( $on ) : ?>
							<a class="seob-btn seob-btn-primary" href="<?php echo esc_url( $module->admin_url() ); ?>"><?php esc_html_e( 'Open', 'seo-boster' ); ?></a>
						<?php endif; ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php SEOB_Security::field( 'seob_toggle_module' ); ?>
							<input type="hidden" name="action" value="seob_toggle_module">
							<input type="hidden" name="module" value="<?php echo esc_attr( $id ); ?>">
							<button type="submit" class="seob-btn seob-btn-ghost"><?php echo $on ? esc_html__( 'Turn off', 'seo-boster' ) : esc_html__( 'Turn on', 'seo-boster' ); ?></button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
			<div class="seob-module-card seob-module-soon">
				<div class="seob-module-icon"><span class="dashicons dashicons-plus-alt2"></span></div>
				<div class="seob-module-body">
					<h3><?php esc_html_e( 'More tools coming', 'seo-boster' ); ?></h3>
					<p><?php esc_html_e( 'New SEO modules plug in here, for example meta tag checks, internal linking or schema.', 'seo-boster' ); ?></p>
				</div>
			</div>
		</div>
		<?php
		self::page_end();
	}

	/* ------------------------------------------------------------------
	 * Shared layout helpers used by every module screen.
	 * ------------------------------------------------------------------ */

	/**
	 * Open a styled admin page.
	 *
	 * @param string $title    Page title.
	 * @param string $subtitle Short description.
	 * @param array  $tabs     slug => label.
	 * @param string $active   Active tab slug.
	 * @param string $base_url URL the tabs link to.
	 */
	public static function page_start( $title, $subtitle = '', array $tabs = array(), $active = '', $base_url = '' ) {
		?>
		<div class="wrap seob-wrap">
			<div class="seob-header">
				<div class="seob-brand">
					<span class="seob-logo dashicons dashicons-chart-line"></span>
					<div>
						<h1><?php echo esc_html( $title ); ?></h1>
						<?php if ( $subtitle ) : ?>
							<p><?php echo esc_html( $subtitle ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<span class="seob-version">SEO Boster v<?php echo esc_html( SEOB_VERSION ); ?></span>
			</div>
			<?php if ( $tabs ) : ?>
				<nav class="seob-tabs">
					<?php foreach ( $tabs as $slug => $label ) : ?>
						<a class="seob-tab <?php echo $slug === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
			<div class="seob-content">
		<?php
	}

	public static function page_end() {
		?>
			</div>
			<p class="seob-footer"><?php echo esc_html( sprintf( /* translators: %s author */ __( 'SEO Boster by %s', 'seo-boster' ), 'Rajvardhan Singh Rathore' ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Show a notice for ?seob_msg=code using a map of code => [type, text].
	 *
	 * @param array $map Messages.
	 */
	public static function notice_from_query( array $map ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$code = isset( $_GET['seob_msg'] ) ? sanitize_key( wp_unslash( $_GET['seob_msg'] ) ) : '';
		if ( '' === $code || ! isset( $map[ $code ] ) ) {
			return;
		}
		list( $type, $text ) = $map[ $code ];
		self::notice( $type, $text );
	}

	public static function notice( $type, $text ) {
		$type = in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info';
		echo '<div class="seob-notice seob-notice-' . esc_attr( $type ) . '">' . esc_html( $text ) . '</div>';
	}
}
