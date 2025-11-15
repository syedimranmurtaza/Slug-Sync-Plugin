<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CASS_Slug_Sync_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_admin_page() {
        add_management_page(
            'CtrlAlt Slug Sync',
            'CtrlAlt Slug Sync',
            'manage_options',
            'cass-slug-sync',
            array( $this, 'render_page' )
        );
    }

    public function register_settings() {
        register_setting(
            'cass_slug_sync_group',
            'cass_slug_sync_mappings',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_mappings' ),
                'default'           => '',
            )
        );
    }

    public function sanitize_mappings( $value ) {
        // Basic cleanup: remove empty lines, keep comments and data lines.
        $lines = preg_split( '/\r\n|\r|\n/', $value );
        $clean = array();
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( $line === '' ) {
                continue;
            }
            $clean[] = $line;
        }
        return implode( "\n", $clean );
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'tools_page_cass-slug-sync' ) {
            return;
        }
        wp_enqueue_script(
            'cass-slug-sync-admin',
            CASS_SLUG_SYNC_URL . 'assets/admin.js',
            array( 'jquery' ),
            CASS_SLUG_SYNC_VERSION,
            true
        );
        wp_localize_script(
            'cass-slug-sync-admin',
            'CASS_Slug_Sync',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'cass_slug_sync_nonce' ),
                'total'    => count( CASS_Slug_Sync::get_mappings() ),
            )
        );
        wp_enqueue_style(
            'cass-slug-sync-admin-css',
            CASS_SLUG_SYNC_URL . 'assets/admin.css',
            array(),
            CASS_SLUG_SYNC_VERSION
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $mappings = CASS_Slug_Sync::get_mappings();
        $raw      = get_option( 'cass_slug_sync_mappings', '' );
        ?>
        <div class="wrap">
            <h1>CtrlAlt Slug Sync (Old → New)</h1>
            <p>
                This tool will update slugs on this site based on the mappings you define below,
                and it will also update internal links in content, Elementor data, and menu custom URLs for those posts and pages.
            </p>
            <p><strong>Tip:</strong> Always make a full database backup before running this, so you can roll back if needed.</p>

            <h2>Slug mappings</h2>
            <p>Enter one mapping per line in this format:</p>
            <p><code>current-slug-on-site,desired-slug-you-want</code></p>
            <p>Lines starting with <code>#</code> are treated as comments and ignored.</p>

            <form method="post" action="options.php" style="margin-bottom:20px;">
                <?php
                settings_fields( 'cass_slug_sync_group' );
                ?>
                <textarea name="cass_slug_sync_mappings" rows="10" cols="80" class="large-text code"><?php echo esc_textarea( $raw ); ?></textarea>
                <?php submit_button( 'Save mappings' ); ?>
            </form>

            <h2>Planned slug changes</h2>
            <?php if ( empty( $mappings ) ) : ?>
                <p><em>No valid mappings found. Please add at least one line above and save.</em></p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Current slug (from)</th>
                            <th>New slug (to)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $mappings as $i => $map ) : ?>
                        <tr>
                            <td><?php echo intval( $i + 1 ); ?></td>
                            <td><code><?php echo esc_html( $map['from_slug'] ); ?></code></td>
                            <td><code><?php echo esc_html( $map['to_slug'] ); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>Run migration</h2>
            <p>
                When you click the button, the tool will process one slug mapping at a time and show progress below.
            </p>
            <button id="cass-start" class="button button-primary button-hero"<?php echo empty( $mappings ) ? ' disabled' : ''; ?>>Run Slug Sync</button>

            <div id="cass-progress-wrapper" style="margin-top:20px;display:none;">
                <div id="cass-progress-text">Starting…</div>
                <div style="background:#eee;border-radius:3px;overflow:hidden;margin-top:8px;">
                    <div id="cass-progress-bar" style="width:0;height:18px;background:#46b450;"></div>
                </div>
                <ul id="cass-log" style="margin-top:15px;max-height:260px;overflow:auto;border:1px solid #ddd;background:#fff;padding:10px;"></ul>
            </div>
        </div>
        <?php
    }
}
