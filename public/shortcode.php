<?php
if (!defined('ABSPATH')) { exit; }

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

    $atts = shortcode_atts(
        array(
            'columns'  => get_option('frcf_courses_columns', 3),
            'location' => '',
            'limit'    => get_option('frcf_courses_per_page', 12),
            'show_all' => 'no',
            'debug'    => 'no',
        ),
        $atts,
        'frcf_courses'
    );

    $columns  = max(2, min(4, intval($atts['columns'])));
    $location = sanitize_text_field($atts['location']);
    $limit    = max(1, intval($atts['limit']));
    $show_all = strtolower($atts['show_all']) === 'yes';
    $debug    = strtolower($atts['debug']) === 'yes';

    $today = current_time('Y-m-d');

    // Construim query
    if ($show_all) {
        if (!empty($location)) {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE location = %s ORDER BY start_date DESC LIMIT %d",
                $location,
                $limit
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY start_date DESC LIMIT %d",
                $limit
            );
        }
    } else {
        // Active = cursuri cu start_date în viitor sau încă în desfășurare
        if (!empty($location)) {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name
                 WHERE location = %s
                   AND (
                        start_date >= %s
                     OR (end_date IS NOT NULL AND end_date <> '' AND end_date <> '0000-00-00' AND end_date >= %s)
                   )
                 ORDER BY start_date ASC
                 LIMIT %d",
                $location,
                $today,
                $today,
                $limit
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name
                 WHERE
                   (
                        start_date >= %s
                     OR (end_date IS NOT NULL AND end_date <> '' AND end_date <> '0000-00-00' AND end_date >= %s)
                   )
                 ORDER BY start_date ASC
                 LIMIT %d",
                $today,
                $today,
                $limit
            );
        }
    }

    $courses   = $wpdb->get_results($query);
    $locations = $wpdb->get_col("SELECT DISTINCT location FROM $table_name WHERE location <> '' ORDER BY location ASC");

    ob_start();

    // Debug (în buffer, nu cu echo direct înainte de ob_start)
    if ($debug) {
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
    <div class="frcf-courses-container" data-columns="<?php echo esc_attr($columns); ?>">

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

        <div class="frcf-courses-grid columns-<?php echo esc_attr($columns); ?>">
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
