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

    // Creează assets dacă nu există
    frcf_create_assets();
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
        'FRCF Cursuri',
        'FRCF Cursuri',
        'manage_options',
        'frcf-courses',
        'frcf_courses_admin_page',
        'dashicons-calendar-alt',
        30
    );

    add_submenu_page(
        'frcf-courses',
        'Toate Cursurile',
        'Toate Cursurile',
        'manage_options',
        'frcf-courses',
        'frcf_courses_admin_page'
    );

    add_submenu_page(
        'frcf-courses',
        'Adaugă Curs Nou',
        'Adaugă Curs Nou',
        'manage_options',
        'frcf-courses-add',
        'frcf_courses_add_page'
    );

    add_submenu_page(
        'frcf-courses',
        'Setări',
        'Setări',
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
            echo '<div class="notice notice-success"><p>Cursul a fost șters!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Nonce invalid. Operațiune anulată.</p></div>';
        }
    }

    $courses = $wpdb->get_results("SELECT * FROM $table_name ORDER BY start_date DESC");
    ?>
    <div class="wrap">
        <h1>FRCF Cursuri <a href="?page=frcf-courses-add" class="page-title-action">Adaugă Nou</a></h1>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Titlu</th>
                    <th>Locație</th>
                    <th>Data Start</th>
                    <th>Data Sfârșit</th>
                    <th>Organizator</th>
                    <th>Acțiuni</th>
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
                        <a href="<?php echo esc_url( add_query_arg(array('page'=>'frcf-courses-add','id'=>$course->id), admin_url('admin.php')) ); ?>" class="button">Editează</a>
                        <?php $nonce = wp_create_nonce('frcf_delete_course_' . $course->id); ?>
                        <a href="<?php echo esc_url( add_query_arg(array('page'=>'frcf-courses','action'=>'delete','id'=>$course->id,'_wpnonce'=>$nonce), admin_url('admin.php')) ); ?>"
                           class="button" onclick="return confirm('Sigur dorești să ștergi acest curs?')">Șterge</a>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7">Nu există cursuri.</td></tr>
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
            ? sanitize_text_field($_POST['location_new'])
            : sanitize_text_field($_POST['location_select']);

        $organizer_value = (isset($_POST['organizer_select']) && $_POST['organizer_select'] === 'new')
            ? sanitize_text_field($_POST['organizer_new'])
            : sanitize_text_field($_POST['organizer_select']);

        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date_raw = sanitize_text_field($_POST['end_date']);
        $end_date = !empty($end_date_raw) ? $end_date_raw : null;

        $data = array(
            'title'       => sanitize_text_field($_POST['title']),
            'image_url'   => esc_url_raw($_POST['image_url']),
            'location'    => $location_value,
            'start_date'  => $start_date,
            'end_date'    => $end_date,
            'organizer'   => $organizer_value,
            'description' => wp_kses_post($_POST['description'])
        );

        if ($course) {
            $wpdb->update($table_name, $data, array('id' => $course->id));
            echo '<div class="notice notice-success"><p>Cursul a fost actualizat!</p></div>';
            $course = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $course->id));
        } else {
            $wpdb->insert($table_name, $data);
            echo '<div class="notice notice-success"><p>Cursul a fost adăugat!</p></div>';
        }

        // Reîncarcă listele
        $existing_locations = $wpdb->get_col("SELECT DISTINCT location FROM $table_name WHERE location != '' ORDER BY location ASC");
        $existing_organizers = $wpdb->get_col("SELECT DISTINCT organizer FROM $table_name WHERE organizer != '' ORDER BY organizer ASC");
    }
    ?>
    <div class="wrap">
        <h1><?php echo $course ? 'Editează Curs' : 'Adaugă Curs Nou'; ?></h1>

        <form method="post" action="">
            <?php wp_nonce_field('frcf_save_course'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="title">Titlu Curs</label></th>
                    <td><input type="text" name="title" id="title" class="regular-text"
                               value="<?php echo $course ? esc_attr($course->title) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="image_url">URL Imagine</label></th>
                    <td>
                        <input type="text" name="image_url" id="image_url" class="large-text"
                               value="<?php echo $course ? esc_url($course->image_url) : ''; ?>">
                        <button type="button" class="button" onclick="selectImage()">Selectează din Media</button>
                        <?php if ($course && $course->image_url): ?>
                            <br><br>
                            <img src="<?php echo esc_url($course->image_url); ?>" style="max-width: 300px; height: auto;">
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="location_select">Locație</label></th>
                    <td>
                        <select name="location_select" id="location_select" onchange="toggleLocationInput()" style="min-width: 250px;">
                            <option value="">-- Selectează Locație --</option>
                            <?php foreach ($existing_locations as $loc): ?>
                                <option value="<?php echo esc_attr($loc); ?>" <?php selected($course && $course->location === $loc); ?>>
                                    <?php echo esc_html($loc); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="new">➕ Adaugă locație nouă</option>
                        </select>
                        <input type="text" name="location_new" id="location_new" class="regular-text"
                               placeholder="Introdu locație nouă" style="display: none; margin-left: 10px;">
                    </td>
                </tr>
                <tr>
                    <th><label for="start_date">Data Start</label></th>
                    <td><input type="date" name="start_date" id="start_date"
                               value="<?php echo $course ? esc_attr($course->start_date) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th><label for="end_date">Data Sfârșit</label></th>
                    <td>
                        <input type="date" name="end_date" id="end_date"
                               value="<?php echo $course ? esc_attr($course->end_date) : ''; ?>">
                        <p class="description">Lasă gol dacă cursul durează o singură zi sau nu are dată de sfârșit.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="organizer_select">Organizator</label></th>
                    <td>
                        <select name="organizer_select" id="organizer_select" onchange="toggleOrganizerInput()" style="min-width: 250px;">
                            <option value="">-- Selectează Organizator --</option>
                            <?php foreach ($existing_organizers as $org): ?>
                                <option value="<?php echo esc_attr($org); ?>" <?php selected($course && $course->organizer === $org); ?>>
                                    <?php echo esc_html($org); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="new">➕ Adaugă organizator nou</option>
                        </select>
                        <input type="text" name="organizer_new" id="organizer_new" class="regular-text"
                               placeholder="Introdu organizator nou" style="display: none; margin-left: 10px;">
                    </td>
                </tr>
                <tr>
                    <th><label for="description">Descriere</label></th>
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
                <input type="submit" class="button-primary" value="<?php echo $course ? 'Actualizează' : 'Adaugă'; ?> Curs">
                <a href="?page=frcf-courses" class="button">Anulează</a>
            </p>
        </form>
    </div>

    <script type="text/javascript">
    function selectImage() {
        var frame = wp.media({
            title: 'Selectează Imagine',
            multiple: false,
            library: {type: 'image'},
            button: {text: 'Folosește Imagine'}
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
        echo '<div class="notice notice-success"><p>Setările au fost salvate!</p></div>';
    }

    $columns = get_option('frcf_courses_columns', 3);
    $per_page = get_option('frcf_courses_per_page', 12);
    ?>
    <div class="wrap">
        <h1>Setări FRCF Cursuri</h1>

        <form method="post" action="">
            <?php wp_nonce_field('frcf_save_settings'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="columns">Număr Coloane</label></th>
                    <td>
                        <select name="columns" id="columns">
                            <option value="2" <?php selected($columns, 2); ?>>2 Coloane</option>
                            <option value="3" <?php selected($columns, 3); ?>>3 Coloane</option>
                            <option value="4" <?php selected($columns, 4); ?>>4 Coloane</option>
                        </select>
                        <p class="description">Numărul de coloane pentru afișarea cursurilor</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="per_page">Cursuri pe Pagină</label></th>
                    <td>
                        <input type="number" name="per_page" id="per_page" value="<?php echo esc_attr($per_page); ?>" min="1" max="50">
                        <p class="description">Numărul de cursuri afișate pe pagină</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button-primary" value="Salvează Setările">
            </p>
        </form>

        <h2>Utilizare Shortcode</h2>
        <p>Folosește shortcode-ul <code>[frcf_courses]</code> pentru a afișa cursurile pe orice pagină sau articol.</p>
        <ul>
            <li><code>[frcf_courses columns="3"]</code> – Setează numărul de coloane</li>
            <li><code>[frcf_courses location="București"]</code> – Afișează doar cursurile dintr-o locație</li>
            <li><code>[frcf_courses limit="6"]</code> – Limitează numărul de cursuri</li>
            <li><code>[frcf_courses show_all="yes"]</code> – Afișează toate cursurile (inclusiv expirate)</li>
            <li><code>[frcf_courses debug="yes"]</code> – Afișează informații de debug</li>
        </ul>
    </div>
    <?php
}

// ===== Frontend Assets =====
add_action('wp_enqueue_scripts', 'frcf_courses_enqueue_scripts');
function frcf_courses_enqueue_scripts() {
    // dacă nu există încă, încearcă să le creezi
    frcf_create_assets();

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
            <h3>Debug Info</h3>
            <p><strong>Query executat:</strong> <?php echo esc_html($query); ?></p>
            <p><strong>Data curentă:</strong> <?php echo esc_html($today); ?></p>
            <p><strong>Număr cursuri găsite:</strong> <?php echo count($courses); ?></p>
            <p><strong>Total cursuri în DB:</strong> <?php echo (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name"); ?></p>
            <?php if (!empty($courses)) : ?>
                <p><strong>Primul curs:</strong></p>
                <pre><?php echo esc_html(print_r($courses[0], true)); ?></pre>
            <?php endif; ?>
        </div>
        <?php
    }
    ?>
    <div class="frcf-courses-container" data-columns="<?php echo esc_attr($atts['columns']); ?>">

        <?php if (is_array($locations) && count($locations) > 1): ?>
        <div class="frcf-filter-container">
            <label for="frcf-location-filter">Filtrează după locație:</label>
            <select id="frcf-location-filter" class="frcf-location-filter">
                <option value="">Toate locațiile</option>
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
                            <a href="#" class="frcf-btn-register">Înscrie-te acum!</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="frcf-no-courses">
                    <p>Nu există cursuri disponibile în acest moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

// ===== Creare assets pe disc (style.css, script.js) =====
function frcf_create_assets() {
    $assets_dir = trailingslashit(FRCF_COURSES_PLUGIN_DIR . 'assets');

    if (!file_exists($assets_dir)) {
        wp_mkdir_p($assets_dir);
    }

    // CSS
    $css_path = $assets_dir . 'style.css';
    if (!file_exists($css_path)) {
        $css_content = '/* FRCF Courses Styles */
.frcf-courses-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
.frcf-filter-container {
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.frcf-filter-container label {
    font-weight: 600;
    color: #333;
}
.frcf-location-filter {
    padding: 8px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 5px;
    font-size: 16px;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.3s;
}
.frcf-location-filter:hover,
.frcf-location-filter:focus {
    border-color: #e31e24;
    outline: none;
}
.frcf-courses-grid {
    display: grid;
    gap: 30px;
    margin-bottom: 40px;
}
.frcf-courses-grid.columns-2 { grid-template-columns: repeat(2, 1fr); }
.frcf-courses-grid.columns-3 { grid-template-columns: repeat(3, 1fr); }
.frcf-courses-grid.columns-4 { grid-template-columns: repeat(4, 1fr); }
@media (max-width: 992px) {
    .frcf-courses-grid.columns-4 { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .frcf-courses-grid.columns-3,
    .frcf-courses-grid.columns-4 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .frcf-courses-grid.columns-2,
    .frcf-courses-grid.columns-3,
    .frcf-courses-grid.columns-4 { grid-template-columns: 1fr; }
}
.frcf-course-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,.1);
    transition: transform .3s, box-shadow .3s;
}
.frcf-course-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0,0,0,.12);
}
.frcf-course-image { aspect-ratio: 16/9; background: #f1f1f1; display: flex; align-items: center; justify-content: center; }
.frcf-course-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
.frcf-no-image .frcf-placeholder { width: 100%; height: 100%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#e31e24; letter-spacing:1px; }
.frcf-course-content { padding: 16px; }
.frcf-course-title { margin: 0 0 8px; font-size: 1.1rem; line-height: 1.3; }
.frcf-course-meta { display: grid; gap: 6px; margin-bottom: 10px; }
.frcf-meta-item { display: flex; align-items: center; gap: 6px; font-size: .95rem; color: #333; }
.frcf-course-description { color: #444; margin-bottom: 12px; }
.frcf-course-action { display: flex; justify-content: flex-end; }
.frcf-btn-register {
    display: inline-block;
    padding: 8px 14px;
    border-radius: 8px;
    background: #e31e24;
    color: #fff;
    text-decoration: none;
    font-weight: 600;
}
.frcf-btn-register:hover { opacity: .9; }
.frcf-no-courses { text-align:center; padding: 30px; background:#fafafa; border-radius:12px; }
';
        file_put_contents($css_path, $css_content);
    }

    // JS
    $js_path = $assets_dir . 'script.js';
    if (!file_exists($js_path)) {
        $js_content = '(function($){
$(document).on("change", ".frcf-location-filter", function(){
    var val = $(this).val();
    var $cards = $(".frcf-course-card");
    if(!val){ $cards.show(); return; }
    $cards.hide().filter(function(){
        return $(this).data("location") == val;
    }).show();
});
})(jQuery);';
        file_put_contents($js_path, $js_content);
    }
}
