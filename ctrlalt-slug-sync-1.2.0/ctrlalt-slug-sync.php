<?php
/**
 * Plugin Name: CtrlAlt Slug Sync (Old → New)
 * Description: Safely update selected slugs to match your desired values and fix internal links in content and menus. You can define unlimited slug mappings in the admin panel and run them with progress tracking.
 * Version: 1.2.0
 * Author: CtrlAltImran
 * License: GPLv2 or later
 * Requires at least: 5.5
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CASS_SLUG_SYNC_VERSION', '1.2.0' );
define( 'CASS_SLUG_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'CASS_SLUG_SYNC_URL', plugin_dir_url( __FILE__ ) );

require_once CASS_SLUG_SYNC_PATH . 'includes/class-cass-slug-sync-admin.php';

class CASS_Slug_Sync {

    /**
     * Get mappings from saved settings.
     * Each mapping is an array with keys: from_slug (current), to_slug (desired).
     */
    public static function get_mappings() {
        $raw = get_option( 'cass_slug_sync_mappings', '' );

        // If empty, prefill with example mappings (you can edit them in the admin area).
        if ( $raw === '' ) {
            $raw = "microneedling,microneedling-2\n"
                 . "nouvaderm-skin-restoration,nouvaderm-skin-restoration-in-northville-mi\n"
                 . "blogs,blog";
            update_option( 'cass_slug_sync_mappings', $raw );
        }

        $lines    = preg_split( '/\r\n|\r|\n/', $raw );
        $mappings = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( $line === '' ) {
                continue;
            }
            if ( strpos( $line, '#' ) === 0 ) {
                // Comment line
                continue;
            }

            // Expected format: current-slug,desired-slug
            $parts = explode( ',', $line );
            if ( count( $parts ) < 2 ) {
                continue;
            }

            $from_slug = sanitize_title( trim( $parts[0] ) );
            $to_slug   = sanitize_title( trim( $parts[1] ) );

            if ( $from_slug === '' || $to_slug === '' ) {
                continue;
            }

            $mappings[] = array(
                'from_slug' => $from_slug,
                'to_slug'   => $to_slug,
            );
        }

        return $mappings;
    }

    public function __construct() {
        new CASS_Slug_Sync_Admin();
        add_action( 'wp_ajax_cass_slug_sync_step', array( $this, 'ajax_step' ) );
    }

    /**
     * Run one migration step (one mapping) via AJAX.
     */
    public function ajax_step() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }
        check_ajax_referer( 'cass_slug_sync_nonce', 'nonce' );

        $index    = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : 0;
        $mappings = self::get_mappings();
        $total    = count( $mappings );

        if ( $index < 0 || $index >= $total ) {
            wp_send_json_success( array(
                'done'   => true,
                'index'  => $index,
                'total'  => $total,
                'status' => 'All done',
            ) );
        }

        $mapping   = $mappings[ $index ];
        $from_slug = sanitize_title( $mapping['from_slug'] );
        $to_slug   = sanitize_title( $mapping['to_slug'] );

        // Find a post or page with the current slug.
        $post = get_page_by_path( $from_slug, OBJECT, array( 'post', 'page' ) );

        if ( ! $post ) {
            wp_send_json_success( array(
                'done'        => false,
                'index'       => $index,
                'total'       => $total,
                'status'      => 'No post or page found for slug ' . $from_slug,
                'slug_from'   => $from_slug,
                'slug_to'     => $to_slug,
                'post_id'     => 0,
                'changes'     => array(),
            ) );
        }

        $post_id = $post->ID;

        // Record the old URL before changing the slug.
        $old_url = get_permalink( $post_id );

        // Update the slug (post_name) to the desired slug.
        wp_update_post( array(
            'ID'        => $post_id,
            'post_name' => $to_slug,
        ) );

        // New URL after slug change.
        $new_url = get_permalink( $post_id );

        global $wpdb;
        $changes = array(
            'content_replacements' => 0,
            'meta_replacements'    => 0,
            'menu_replacements'    => 0,
        );

        // 1) Update URLs in post content (all post types).
        $content_sql = $wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)",
            $old_url,
            $new_url
        );
        $wpdb->query( $content_sql );
        $changes['content_replacements'] = $wpdb->rows_affected;

        // 2) Update Elementor data in postmeta (if any).
        $meta_sql = $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)
             WHERE meta_key = '_elementor_data' AND meta_value LIKE %s",
            $old_url,
            $new_url,
            '%' . $wpdb->esc_like( $old_url ) . '%'
        );
        $wpdb->query( $meta_sql );
        $changes['meta_replacements'] = $wpdb->rows_affected;

        // 3) Update nav menu item custom URLs.
        $menu_sql = $wpdb->prepare(
            "UPDATE {$wpdb->postmeta}
             SET meta_value = REPLACE(meta_value, %s, %s)
             WHERE meta_key = '_menu_item_url' AND meta_value LIKE %s",
            $old_url,
            $new_url,
            '%' . $wpdb->esc_like( $old_url ) . '%'
        );
        $wpdb->query( $menu_sql );
        $changes['menu_replacements'] = $wpdb->rows_affected;

        wp_send_json_success( array(
            'done'      => false,
            'index'     => $index,
            'total'     => $total,
            'status'    => 'Updated slug and internal links.',
            'slug_from' => $from_slug,
            'slug_to'   => $to_slug,
            'post_id'   => $post_id,
            'old_url'   => $old_url,
            'new_url'   => $new_url,
            'changes'   => $changes,
        ) );
    }
}

new CASS_Slug_Sync();
