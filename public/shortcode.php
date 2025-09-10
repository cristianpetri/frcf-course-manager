<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frcf_Courses_Shortcode {

    public static function register() {
        add_shortcode( 'frcf_courses', array( __CLASS__, 'output' ) );
    }

    protected static function enqueue_assets() {
        wp_enqueue_style(
            'frcf-courses-style',
            FRCF_COURSES_PLUGIN_URL . 'assets/style.css',
            array(),
            FRCF_COURSES_VERSION
        );

        wp_enqueue_script(
            'frcf-courses-script',
            FRCF_COURSES_PLUGIN_URL . 'assets/script.js',
            array( 'jquery' ),
            FRCF_COURSES_VERSION,
            true
        );

        wp_localize_script(
            'frcf-courses-script',
            'frcf_ajax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'frcf_courses_nonce' ),
            )
        );
    }

    protected static function build_query( $args ) {
        global $wpdb;

        $table = FRCF_COURSES_TABLE;
        $today = current_time( 'Y-m-d' );

        $sql              = "SELECT * FROM $table";
        $where_conditions = array();
        $prepare_values   = array();

        if ( ! empty( $args['location'] ) ) {
            $where_conditions[] = 'location = %s';
            $prepare_values[]   = $args['location'];
        }

        if ( ! $args['show_all'] ) {
            $where_conditions[] = '( start_date >= %s OR ( end_date IS NOT NULL AND end_date <> "" AND end_date <> "0000-00-00" AND end_date >= %s ) )';
            $prepare_values[]   = $today;
            $prepare_values[]   = $today;
        }

        if ( ! empty( $where_conditions ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where_conditions );
        }

        $order = $args['show_all'] ? 'DESC' : 'ASC';
        $sql  .= " ORDER BY start_date $order";

        $sql            .= ' LIMIT %d';
        $prepare_values[] = $args['limit'];

        if ( ! empty( $prepare_values ) ) {
            return $wpdb->prepare( $sql, $prepare_values );
        }

        return $sql;
    }

// Înlocuiește temporar metoda output din public/shortcode.php 
// pentru a avea mai multe informații de debug

public static function output( $atts ) {
    global $wpdb;

    self::enqueue_assets();

    $atts = shortcode_atts(
        array(
            'columns'  => get_option( 'frcf_courses_columns', 3 ),
            'location' => '',
            'limit'    => get_option( 'frcf_courses_per_page', 12 ),
            'show_all' => 'no',
            'debug'    => 'no',
        ),
        $atts,
        'frcf_courses'
    );

    $args = array(
        'columns'  => max( 2, min( 4, (int) $atts['columns'] ) ),
        'location' => sanitize_text_field( $atts['location'] ),
        'limit'    => max( 1, (int) $atts['limit'] ),
        'show_all' => in_array( strtolower( $atts['show_all'] ), array( '1', 'true', 'yes' ), true ),
        'debug'    => in_array( strtolower( $atts['debug'] ), array( '1', 'true', 'yes' ), true ),
    );

    $sql       = self::build_query( $args );
    $courses   = $wpdb->get_results( $sql );
    $locations = $wpdb->get_col( 'SELECT DISTINCT location FROM ' . FRCF_COURSES_TABLE . " WHERE location <> '' ORDER BY location ASC" );

    ob_start();

    if ( $args['debug'] ) {
        echo '<div class="frcf-debug" style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px;">';
        echo '<h3>🐛 DEBUG INFO</h3>';
        
        // Time zone și data
        echo '<p><strong>WordPress Time:</strong> ' . current_time('Y-m-d H:i:s') . '</p>';
        echo '<p><strong>PHP Time:</strong> ' . date('Y-m-d H:i:s') . '</p>';
        echo '<p><strong>Today used in query:</strong> ' . current_time('Y-m-d') . '</p>';
        
        // Argumentele procesate
        echo '<p><strong>Args:</strong> ' . esc_html(print_r($args, true)) . '</p>';
        
        // Query-ul final
        echo '<p><strong>Generated SQL:</strong></p>';
        echo '<pre style="background: #fff; padding: 10px; overflow-x: auto;">' . esc_html( $sql ) . '</pre>';
        
        // Toate cursurile din DB (pentru comparație)
        $all_courses = $wpdb->get_results("SELECT id, title, location, start_date, end_date FROM " . FRCF_COURSES_TABLE . " ORDER BY id");
        echo '<p><strong>All courses in DB:</strong></p>';
        if ($all_courses) {
            echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
            echo '<tr><th>ID</th><th>Title</th><th>Location</th><th>Start Date</th><th>End Date</th></tr>';
            foreach ($all_courses as $c) {
                echo '<tr>';
                echo '<td>' . $c->id . '</td>';
                echo '<td>' . esc_html($c->title) . '</td>';
                echo '<td>' . esc_html($c->location) . '</td>';
                echo '<td>' . $c->start_date . '</td>';
                echo '<td>' . ($c->end_date ?: 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p>No courses found in database!</p>';
        }
        
        // Rezultatele query-ului
        echo '<p><strong>Query Results:</strong> ' . count($courses) . ' courses found</p>';
        if ($courses) {
            foreach ($courses as $course) {
                echo '<div style="margin: 10px 0; padding: 10px; background: #e8f5e8; border-radius: 3px;">';
                echo '<strong>' . esc_html($course->title) . '</strong><br>';
                echo 'Location: ' . esc_html($course->location) . '<br>';
                echo 'Start: ' . $course->start_date . '<br>';
                echo 'End: ' . ($course->end_date ?: 'NULL') . '<br>';
                echo '</div>';
            }
        }
        
        echo '</div>';
    }
    ?>
    <div class="frcf-courses-container" data-columns="<?php echo esc_attr( $args['columns'] ); ?>">
        <?php if ( is_array( $locations ) && count( $locations ) > 1 ) : ?>
            <div class="frcf-filter-container">
                <label for="frcf-location-filter"><?php echo esc_html__( 'Filtrează după locație:', 'frcf-courses' ); ?></label>
                <select id="frcf-location-filter" class="frcf-location-filter">
                    <option value=""><?php echo esc_html__( 'Toate locațiile', 'frcf-courses' ); ?></option>
                    <?php foreach ( $locations as $loc ) : ?>
                        <option value="<?php echo esc_attr( $loc ); ?>"><?php echo esc_html( $loc ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="frcf-courses-grid columns-<?php echo esc_attr( $args['columns'] ); ?>">
            <?php if ( $courses ) : ?>
                <?php foreach ( $courses as $course ) : ?>
                    <?php
                    $date_display = date( 'd.m.Y', strtotime( $course->start_date ) );
                    if ( ! empty( $course->end_date ) && '0000-00-00' !== $course->end_date ) {
                        $date_display .= ' - ' . date( 'd.m.Y', strtotime( $course->end_date ) );
                    }
                    ?>
                    <div class="frcf-course-card" data-location="<?php echo esc_attr( $course->location ); ?>">
                        <?php if ( ! empty( $course->image_url ) ) : ?>
                            <div class="frcf-course-image">
                                <img src="<?php echo esc_url( $course->image_url ); ?>" alt="<?php echo esc_attr( $course->title ); ?>" />
                            </div>
                        <?php else : ?>
                            <div class="frcf-course-image frcf-no-image">
                                <div class="frcf-placeholder"><span>FRCF</span></div>
                            </div>
                        <?php endif; ?>

                        <div class="frcf-course-content">
                            <h3 class="frcf-course-title"><?php echo esc_html( $course->title ); ?></h3>

                            <div class="frcf-course-meta">
                                <div class="frcf-meta-item">
                                    <span class="frcf-icon">📍</span>
                                    <span class="frcf-meta-text"><?php echo esc_html( $course->location ); ?></span>
                                </div>

                                <div class="frcf-meta-item">
                                    <span class="frcf-icon">📅</span>
                                    <span class="frcf-meta-text"><?php echo esc_html( $date_display ); ?></span>
                                </div>

                                <?php if ( ! empty( $course->organizer ) ) : ?>
                                    <div class="frcf-meta-item">
                                        <span class="frcf-icon">👤</span>
                                        <span class="frcf-meta-text"><?php echo esc_html( $course->organizer ); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ( ! empty( $course->description ) ) : ?>
                                <div class="frcf-course-description">
                                    <?php echo wp_kses_post( wpautop( wp_trim_words( $course->description, 20 ) ) ); ?>
                                </div>
                            <?php endif; ?>

                            <div class="frcf-course-action">
                                <a href="#" class="frcf-btn-register"><?php echo esc_html__( 'Înscrie-te acum!', 'frcf-courses' ); ?></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="frcf-no-courses">
                    <p><?php echo esc_html__( 'Nu există cursuri disponibile în acest moment.', 'frcf-courses' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

Frcf_Courses_Shortcode::register();
