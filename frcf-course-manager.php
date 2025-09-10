<?php
/**
 * Plugin Name: FRCF Course Manager
 * Plugin URI: https://yourdomain.com/
 * Description: Modul pentru afișarea cursurilor cu filtrare după locație și expirare automată
 * Version: 1.1.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: frcf-courses
 */

if (!defined('ABSPATH')) { exit; }

// Constante
define('FRCF_COURSES_VERSION', '1.1.0');
define('FRCF_COURSES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FRCF_COURSES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FRCF_COURSES_TABLE', $GLOBALS['wpdb']->prefix . 'frcf_courses');

// ===== Activare / Dezactivare =====
register_activation_hook(__FILE__, 'frcf_courses_activate');
function frcf_courses_activate() {
    global $wpdb;

    $table_name = FRCF_COURSES_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        image_url text,
        location varchar(255) NOT NULL,
        start_date date NOT NULL,
        end_date date,
        organizer text,
        category varchar(255),
        description longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY start_date (start_date),
        KEY end_date (end_date),
        KEY location (location),
        KEY category (category)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option('frcf_courses_db_version', FRCF_COURSES_VERSION);

    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'frcf_courses_deactivate');
function frcf_courses_deactivate() {
    flush_rewrite_rules();
}

// Ensure database schema is up to date
add_action('plugins_loaded', 'frcf_courses_maybe_upgrade');
function frcf_courses_maybe_upgrade() {
    global $wpdb;
    $table = FRCF_COURSES_TABLE;
    $column = $wpdb->get_results( $wpdb->prepare("SHOW COLUMNS FROM $table LIKE %s", 'category') );
    if ( empty( $column ) ) {
        $wpdb->query("ALTER TABLE $table ADD category varchar(255) AFTER organizer, ADD KEY category (category)");
    }

    $organizer_col = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'organizer'");
    if ( $organizer_col && stripos( $organizer_col->Type, 'text' ) === false ) {
        $wpdb->query("ALTER TABLE $table MODIFY organizer text");
    }
}


require_once FRCF_COURSES_PLUGIN_DIR . 'admin/admin-pages.php';
require_once FRCF_COURSES_PLUGIN_DIR . 'public/shortcode.php';
