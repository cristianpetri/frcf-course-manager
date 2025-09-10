<?php
/**
 * Plugin Name: FRCF Course Manager
 * Plugin URI: https://yourdomain.com/
 * Description: Modul pentru afișarea cursurilor cu filtrare după locație și expirare automată
 * Version: 1.0.1
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: frcf-courses
 */

if (!defined('ABSPATH')) { exit; }

// Constante
define('FRCF_COURSES_VERSION', '1.0.1');
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
        organizer varchar(255),
        description longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY start_date (start_date),
        KEY end_date (end_date),
        KEY location (location)
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

// ===== Admin Menu =====
add_action('admin_menu', 'frcf_courses_admin_menu');
function frcf_courses_admin_menu() {
    add_menu_page(
        __('FRCF Cursuri', 'frcf-courses'),
        __('FRCF Cursuri', 'frcf-courses'),
        'manage_options',
        'frcf-courses',
        'frcf_courses_admin_page',
        'dashicons-calendar-alt',
        30
    );

    add_submenu_page(
        'frcf-courses',
        __('Toate Cursurile', 'frcf-courses'),
        __('Toate Cursurile', 'frcf-courses'),
        'manage_options',
        'frcf-courses',
        'frcf_courses_admin_page'
    );

    add_submenu_page(
        'frcf-courses',
        __('Adaugă Curs Nou', 'frcf-courses'),
        __('Adaugă Curs Nou', 'frcf-courses'),
        'manage_options',
        'frcf-courses-add',
        'frcf_courses_add_page'
    );

    add_submenu_page(
        'frcf-courses',
        __('Setări', 'frcf-courses'),
        __('Setări', 'frcf-courses'),
        'manage_options',
        'frcf-courses-settings',
        'frcf_courses_settings_page'
    );
}

// Pentru media frame în admin (selectare imagine)
add_action('admin_enqueue_scripts', function($hook){
    if (strpos($hook, 'frcf-courses') !== false) {
        wp_enqueue_media();
    }
});

// ===== Pagini Admin =====
function frcf_courses_admin_page() {
    if (!current_user_can('manage_options')) { return; }
    global $wpdb;
    $table_name = FRCF_COURSES_TABLE;

    // Ștergere curs (cu nonce)
    if (isset($_GET['action'], $_GET['id'], $_GET['_wpnonce']) && $_GET['action'] === 'delete') {
        if (wp_verify_nonce($_GET['_wpnonce'], 'frcf_delete_course_' . intval($_GET['id']))) {
            $wpdb->delete($table_name, array('id' => intval($_GET['id'])));
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Cursul a fost șters!', 'frcf-courses' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Nonce invalid. Operațiune anulată.', 'frcf-courses' ) . '</p></div>';
        }
    }

    $courses = $wpdb->get_results("SELECT * FROM $table_name ORDER BY start_date DESC");
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'FRCF Cursuri', 'frcf-courses' ); ?> <a href="?page=frcf-courses-add" class="page-title-action"><?php echo esc_html__( 'Adaugă Nou', 'frcf-courses' ); ?></a></h1>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'ID', 'frcf-courses' ); ?></th>
                    <th><?php echo esc_html__( 'Titlu', 'frcf-courses' ); ?></th>
                    <th><?php echo esc_html__( 'Locație', 'frcf-courses' ); ?></th>
                    <th><?php echo esc_html__( 'Data Start', 'frcf-courses' ); ?></th>
                    <th><?php echo esc_html__( 'Data Sfârșit', 'frcf-courses' ); ?></th>
                    <th><?php echo esc_html__( 'Organizator', 'frcf-courses' ); ?></th>
                    <th><?php echo esc_html__( 'Acțiuni', 'frcf-courses' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($courses): foreach ($courses as $course): ?>
                <tr>
                    <td><?php echo (int) $course->id; ?></td>
                    <td><?php echo esc_html($course->title); ?></td>
                    <td><?php echo esc_html($course->location); ?></td>
                    <td><?php echo esc_html(date('d.m.Y', strtotime($course->start_date))); ?></td>
                    <td><?php echo $course->end_date ? esc_html(date('d.m.Y', strtotime($course->end_date))) : '-'; ?></td>
                    <td><?php echo esc_html($course->organizer); ?></td>
                    <td>
                        <a href="<?php echo esc_url( add_query_arg(array('page'=>'frcf-courses-add','id'=>$course->id), admin_url('admin.php')) ); ?>" class="button"><?php echo esc_html__( 'Editează', 'frcf-courses' ); ?></a>
                        <?php $nonce = wp_create_nonce('frcf_delete_course_' . $course->id); ?>
                        <a href="<?php echo esc_url( add_query_arg(array('page'=>'frcf-courses','action'=>'delete','id'=>$course->id,'_wpnonce'=>$nonce), admin_url('admin.php')) ); ?>"
                           class="button" onclick="return confirm('<?php echo esc_js( esc_html__( 'Sigur dorești să ștergi acest curs?', 'frcf-courses' ) ); ?>')"><?php echo esc_html__( 'Șterge', 'frcf-courses' ); ?></a>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7"><?php echo esc_html__( 'Nu există cursuri.', 'frcf-courses' ); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function frcf_courses_add_page() {
    if (!current_user_can('manage_options')) { return; }
    global $wpdb;
    $table_name = FRCF_COURSES_TABLE;

    $course = null;
    if (isset($_GET['id'])) {
        $course = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
    }

    // Liste existente
    $existing_locations = $wpdb->get_col("SELECT DISTINCT location FROM $table_name WHERE location != '' ORDER BY location ASC");
    $existing_organizers = $wpdb->get_col("SELECT DISTINCT organizer FROM $table_name WHERE organizer != '' ORDER BY organizer ASC");

    // Procesare formular
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('frcf_save_course')) {
        $location_value  = (isset($_POST['location_select']) && $_POST['location_select'] === 'new')
            ? sanitize_text_field( wp_unslash( $_POST['location_new'] ) )
            : sanitize_text_field( wp_unslash( $_POST['location_select'] ) );

        $organizer_value = (isset($_POST['organizer_select']) && $_POST['organizer_select'] === 'new')
            ? sanitize_text_field( wp_unslash( $_POST['organizer_new'] ) )
            : sanitize_text_field( wp_unslash( $_POST['organizer_select'] ) );

        $start_date = sanitize_text_field( wp_unslash( $_POST['start_date'] ) );
        $end_date_raw = sanitize_text_field( wp_unslash( $_POST['end_date'] ) );
        $end_date = !empty($end_date_raw) ? $end_date_raw : null;

        $data = array(
            'title'       => sanitize_text_field( wp_unslash( $_POST['title'] ) ),
            'image_url'   => esc_url_raw( wp_unslash( $_POST['image_url'] ) ),
            'location'    => $location_value,
            'start_date'  => $start_date,
            'end_date'    => $end_date,
            'organizer'   => $organizer_value,
            'description' => wp_kses_post( wp_unslash( $_POST['description'] ) )
        );

        if ($course) {
            $wpdb->update($table_name, $data, array('id' => $course->id));
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Cursul a fost actualizat!', 'frcf-courses' ) . '</p></div>';
            $course = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $course->id));
        } else {
            $wpdb->insert($table_name, $data);
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Cursul a fost adăugat!', 'frcf-courses' ) . '</p></div>';
        }

        // Reîncarcă listele
        $existing_locations = $wpdb->get_col("SELECT DISTINCT location FROM $table_name WHERE location != '' ORDER BY location ASC");
        $existing_organizers = $wpdb->get_col("SELECT DISTINCT organizer FROM $table_name WHERE organizer != '' ORDER BY organizer ASC");
    }
    ?>
    <div class="wrap">
        <h1><?php echo $course ? esc_html__( 'Editează Curs', 'frcf-courses' ) : esc_html__( 'Adaugă Curs Nou', 'frcf-courses' ); ?></h1>

        <form method="post" action="">
            <?php wp_nonce_field('frcf_save_course'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="title"><?php echo esc_html__( 'Titlu Curs', 'frcf-courses' ); ?></label></th>
                    <td><input type="text" name="title" id="title" class="regular-text"
                               value="<?php echo $course ? esc_attr($course->title) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="image_url"><?php echo esc_html__( 'URL Imagine', 'frcf-courses' ); ?></label></th>
                    <td>
                        <input type="text" name="image_url" id="image_url" class="large-text"
                               value="<?php echo $course ? esc_url($course->image_url) : ''; ?>">
                        <button type="button" class="button" onclick="selectImage()"><?php echo esc_html__( 'Selectează din Media', 'frcf-courses' ); ?></button>
                        <?php if ($course && $course->image_url): ?>
                            <br><br>
                            <img src="<?php echo esc_url($course->image_url); ?>" style="max-width: 300px; height: auto;">
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="location_select"><?php echo esc_html__( 'Locație', 'frcf-courses' ); ?></label></th>
                    <td>
                        <select name="location_select" id="location_select" onchange="toggleLocationInput()" style="min-width: 250px;">
                            <option value=""><?php echo esc_html__( '-- Selectează Locație --', 'frcf-courses' ); ?></option>
                            <?php foreach ($existing_locations as $loc): ?>
                                <option value="<?php echo esc_attr($loc); ?>" <?php selected($course && $course->location === $loc); ?>>
                                    <?php echo esc_html($loc); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="new"><?php echo esc_html__( '➕ Adaugă locație nouă', 'frcf-courses' ); ?></option>
                        </select>
                        <input type="text" name="location_new" id="location_new" class="regular-text"
                               placeholder="<?php echo esc_attr__( 'Introdu locație nouă', 'frcf-courses' ); ?>" style="display: none; margin-left: 10px;">
                    </td>
                </tr>
                <tr>
                    <th><label for="start_date"><?php echo esc_html__( 'Data Start', 'frcf-courses' ); ?></label></th>
                    <td><input type="date" name="start_date" id="start_date"
                               value="<?php echo $course ? esc_attr($course->start_date) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="end_date"><?php echo esc_html__( 'Data Sfârșit', 'frcf-courses' ); ?></label></th>
                    <td>
                        <input type="date" name="end_date" id="end_date"
                               value="<?php echo $course ? esc_attr($course->end_date) : ''; ?>">
                        <p class="description"><?php echo esc_html__( 'Lasă gol dacă cursul durează o singură zi sau nu are dată de sfârșit.', 'frcf-courses' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="organizer_select"><?php echo esc_html__( 'Organizator', 'frcf-courses' ); ?></label></th>
                    <td>
                        <select name="organizer_select" id="organizer_select" onchange="toggleOrganizerInput()" style="min-width: 250px;">
                            <option value=""><?php echo esc_html__( '-- Selectează Organizator --', 'frcf-courses' ); ?></option>
                            <?php foreach ($existing_organizers as $org): ?>
                                <option value="<?php echo esc_attr($org); ?>" <?php selected($course && $course->organizer === $org); ?>>
                                    <?php echo esc_html($org); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="new"><?php echo esc_html__( '➕ Adaugă organizator nou', 'frcf-courses' ); ?></option>
                        </select>
                        <input type="text" name="organizer_new" id="organizer_new" class="regular-text"
                               placeholder="<?php echo esc_attr__( 'Introdu organizator nou', 'frcf-courses' ); ?>" style="display: none; margin-left: 10px;">
                    </td>
                </tr>
                <tr>
                    <th><label for="description"><?php echo esc_html__( 'Descriere', 'frcf-courses' ); ?></label></th>
                    <td>
                        <?php
                        wp_editor(
                            $course ? $course->description : '',
                            'description',
                            array('textarea_rows' => 10)
                        );
                        ?>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button-primary" value="<?php echo $course ? esc_attr__( 'Actualizează Curs', 'frcf-courses' ) : esc_attr__( 'Adaugă Curs', 'frcf-courses' ); ?>">
                <a href="?page=frcf-courses" class="button"><?php echo esc_html__( 'Anulează', 'frcf-courses' ); ?></a>
            </p>
        </form>
    </div>

    <script type="text/javascript">
    function selectImage() {
        var frame = wp.media({
            title: '<?php echo esc_js( esc_html__( 'Selectează Imagine', 'frcf-courses' ) ); ?>',
            multiple: false,
            library: {type: 'image'},
            button: {text: '<?php echo esc_js( esc_html__( 'Folosește Imagine', 'frcf-courses' ) ); ?>'}
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            document.getElementById('image_url').value = attachment.url;
        });

        frame.open();
    }

    function toggleLocationInput() {
        var select = document.getElementById('location_select');
        var input = document.getElementById('location_new');

        if (select.value === 'new') {
            input.style.display = 'inline-block';
            input.required = true;
        } else {
            input.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }

    function toggleOrganizerInput() {
        var select = document.getElementById('organizer_select');
        var input = document.getElementById('organizer_new');

        if (select.value === 'new') {
            input.style.display = 'inline-block';
        } else {
            input.style.display = 'none';
            input.value = '';
        }
    }
    </script>
    <?php
}

function frcf_courses_settings_page() {
    if (!current_user_can('manage_options')) { return; }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('frcf_save_settings')) {
        update_option('frcf_courses_columns', max(2, min(4, intval($_POST['columns']))));
        update_option('frcf_courses_per_page', max(1, min(50, intval($_POST['per_page']))));
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Setările au fost salvate!', 'frcf-courses' ) . '</p></div>';
    }

    $columns = get_option('frcf_courses_columns', 3);
    $per_page = get_option('frcf_courses_per_page', 12);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Setări FRCF Cursuri', 'frcf-courses' ); ?></h1>

        <form method="post" action="">
            <?php wp_nonce_field('frcf_save_settings'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="columns"><?php echo esc_html__( 'Număr Coloane', 'frcf-courses' ); ?></label></th>
                    <td>
                        <select name="columns" id="columns">
                            <option value="2" <?php selected($columns, 2); ?>><?php echo esc_html__( '2 Coloane', 'frcf-courses' ); ?></option>
                            <option value="3" <?php selected($columns, 3); ?>><?php echo esc_html__( '3 Coloane', 'frcf-courses' ); ?></option>
                            <option value="4" <?php selected($columns, 4); ?>><?php echo esc_html__( '4 Coloane', 'frcf-courses' ); ?></option>
                        </select>
                        <p class="description"><?php echo esc_html__( 'Numărul de coloane pentru afișarea cursurilor', 'frcf-courses' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="per_page"><?php echo esc_html__( 'Cursuri pe Pagină', 'frcf-courses' ); ?></label></th>
                    <td>
                        <input type="number" name="per_page" id="per_page" value="<?php echo esc_attr($per_page); ?>" min="1" max="50">
                        <p class="description"><?php echo esc_html__( 'Numărul de cursuri afișate pe pagină', 'frcf-courses' ); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button-primary" value="<?php echo esc_attr__( 'Salvează Setările', 'frcf-courses' ); ?>">
            </p>
        </form>

        <h2><?php echo esc_html__( 'Utilizare Shortcode', 'frcf-courses' ); ?></h2>
        <p><?php echo esc_html__( 'Folosește shortcode-ul', 'frcf-courses' ); ?> <code>[frcf_courses]</code> <?php echo esc_html__( 'pentru a afișa cursurile pe orice pagină sau articol.', 'frcf-courses' ); ?></p>
        <ul>
            <li><code>[frcf_courses columns="3"]</code> – <?php echo esc_html__( 'Setează numărul de coloane', 'frcf-courses' ); ?></li>
            <li><code>[frcf_courses location="București"]</code> – <?php echo esc_html__( 'Afișează doar cursurile dintr-o locație', 'frcf-courses' ); ?></li>
            <li><code>[frcf_courses limit="6"]</code> – <?php echo esc_html__( 'Limitează numărul de cursuri', 'frcf-courses' ); ?></li>
            <li><code>[frcf_courses show_all="yes"]</code> – <?php echo esc_html__( 'Afișează toate cursurile (inclusiv expirate)', 'frcf-courses' ); ?></li>
            <li><code>[frcf_courses debug="yes"]</code> – <?php echo esc_html__( 'Afișează informații de debug', 'frcf-courses' ); ?></li>
        </ul>
    </div>
    <?php
}

// ===== Frontend Assets =====
add_action('wp_enqueue_scripts', 'frcf_courses_enqueue_scripts');
function frcf_courses_enqueue_scripts() {
    wp_enqueue_style('frcf-courses-style', FRCF_COURSES_PLUGIN_URL . 'assets/style.css', array(), FRCF_COURSES_VERSION);
    wp_enqueue_script('frcf-courses-script', FRCF_COURSES_PLUGIN_URL . 'assets/script.js', array('jquery'), FRCF_COURSES_VERSION, true);

    wp_localize_script('frcf-courses-script', 'frcf_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('frcf_courses_nonce')
    ));
}

// ===== Shortcode =====
add_shortcode('frcf_courses', 'frcf_courses_shortcode');
function frcf_courses_shortcode($atts) {
    global $wpdb;
    $table_name = FRCF_COURSES_TABLE;

    $atts = shortcode_atts(array(
        'columns'  => get_option('frcf_courses_columns', 3),
        'location' => '',
        'limit'    => get_option('frcf_courses_per_page', 12),
        'show_all' => 'no',
        'debug'    => 'no'
    ), $atts, 'frcf_courses');

    $today = date('Y-m-d');

    // Construim query
    if ($atts['show_all'] === 'yes') {
        if (!empty($atts['location'])) {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE location = %s ORDER BY start_date DESC LIMIT %d",
                $atts['location'],
                intval($atts['limit'])
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY start_date DESC LIMIT %d",
                intval($atts['limit'])
            );
        }
    } else {
        // Active = nu sunt în trecut complet (dacă există end_date, trebuie >= azi; dacă nu există end_date, start_date >= azi)
        if (!empty($atts['location'])) {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name
                 WHERE location = %s
                   AND (
                        (end_date IS NOT NULL AND end_date <> '' AND end_date <> '0000-00-00' AND end_date >= %s)
                     OR ( (end_date IS NULL OR end_date = '' OR end_date = '0000-00-00') AND start_date >= %s )
                   )
                 ORDER BY start_date ASC
                 LIMIT %d",
                $atts['location'], $today, $today, intval($atts['limit'])
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name
                 WHERE
                   (
                        (end_date IS NOT NULL AND end_date <> '' AND end_date <> '0000-00-00' AND end_date >= %s)
                     OR ( (end_date IS NULL OR end_date = '' OR end_date = '0000-00-00') AND start_date >= %s )
                   )
                 ORDER BY start_date ASC
                 LIMIT %d",
                $today, $today, intval($atts['limit'])
            );
        }
    }

    $courses = $wpdb->get_results($query);
    $locations = $wpdb->get_col("SELECT DISTINCT location FROM $table_name WHERE location <> '' ORDER BY location ASC");

    ob_start();

    // Debug (în buffer, nu cu echo direct înainte de ob_start)
    if ($atts['debug'] === 'yes') {
        ?>
        <div style="background:#f0f0f0;padding:20px;margin:20px 0;border:1px solid #ccc;">
            <h3><?php echo esc_html__( 'Debug Info', 'frcf-courses' ); ?></h3>
            <p><strong><?php echo esc_html__( 'Query executat:', 'frcf-courses' ); ?></strong> <?php echo esc_html($query); ?></p>
            <p><strong><?php echo esc_html__( 'Data curentă:', 'frcf-courses' ); ?></strong> <?php echo esc_html($today); ?></p>
            <p><strong><?php echo esc_html__( 'Număr cursuri găsite:', 'frcf-courses' ); ?></strong> <?php echo count($courses); ?></p>
            <p><strong><?php echo esc_html__( 'Total cursuri în DB:', 'frcf-courses' ); ?></strong> <?php echo (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name"); ?></p>
            <?php if (!empty($courses)) : ?>
                <p><strong><?php echo esc_html__( 'Primul curs:', 'frcf-courses' ); ?></strong></p>
                <pre><?php echo esc_html(print_r($courses[0], true)); ?></pre>
            <?php endif; ?>
        </div>
        <?php
    }
    ?>
    <div class="frcf-courses-container" data-columns="<?php echo esc_attr($atts['columns']); ?>">

        <?php if (is_array($locations) && count($locations) > 1): ?>
        <div class="frcf-filter-container">
            <label for="frcf-location-filter"><?php echo esc_html__( 'Filtrează după locație:', 'frcf-courses' ); ?></label>
            <select id="frcf-location-filter" class="frcf-location-filter">
                <option value=""><?php echo esc_html__( 'Toate locațiile', 'frcf-courses' ); ?></option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo esc_attr($loc); ?>"><?php echo esc_html($loc); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="frcf-courses-grid columns-<?php echo esc_attr($atts['columns']); ?>">
            <?php if ($courses): foreach ($courses as $course): ?>
                <?php
                $date_display = date('d.m.Y', strtotime($course->start_date));
                if (!empty($course->end_date) && $course->end_date !== '0000-00-00') {
                    $date_display .= ' - ' . date('d.m.Y', strtotime($course->end_date));
                }
                ?>
                <div class="frcf-course-card" data-location="<?php echo esc_attr($course->location); ?>">
                    <?php if (!empty($course->image_url)): ?>
                        <div class="frcf-course-image">
                            <img src="<?php echo esc_url($course->image_url); ?>" alt="<?php echo esc_attr($course->title); ?>">
                        </div>
                    <?php else: ?>
                        <div class="frcf-course-image frcf-no-image">
                            <div class="frcf-placeholder"><span>FRCF</span></div>
                        </div>
                    <?php endif; ?>

                    <div class="frcf-course-content">
                        <h3 class="frcf-course-title"><?php echo esc_html($course->title); ?></h3>

                        <div class="frcf-course-meta">
                            <div class="frcf-meta-item">
                                <span class="frcf-icon">📍</span>
                                <span class="frcf-meta-text"><?php echo esc_html($course->location); ?></span>
                            </div>

                            <div class="frcf-meta-item">
                                <span class="frcf-icon">📅</span>
                                <span class="frcf-meta-text"><?php echo esc_html($date_display); ?></span>
                            </div>

                            <?php if (!empty($course->organizer)): ?>
                            <div class="frcf-meta-item">
                                <span class="frcf-icon">👤</span>
                                <span class="frcf-meta-text"><?php echo esc_html($course->organizer); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($course->description)): ?>
                            <div class="frcf-course-description">
                                <?php echo wp_kses_post( wpautop( wp_trim_words($course->description, 20) ) ); ?>
                            </div>
                        <?php endif; ?>

                        <div class="frcf-course-action">
                            <a href="#" class="frcf-btn-register"><?php echo esc_html__( 'Înscrie-te acum!', 'frcf-courses' ); ?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="frcf-no-courses">
                    <p><?php echo esc_html__( 'Nu există cursuri disponibile în acest moment.', 'frcf-courses' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

