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

        $where  = array();
        $params = array();

        if ( ! empty( $args['location'] ) ) {
            $where[]  = 'location = %s';
            $params[] = $args['location'];
        }

        if ( ! $args['show_all'] ) {
            $where[]  = '( start_date >= %s OR ( end_date IS NOT NULL AND end_date <> "" AND end_date <> "0000-00-00" AND end_date >= %s ) )';
            $params[] = $today;
            $params[] = $today;
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $order     = $args['show_all'] ? 'DESC' : 'ASC';

        $params[] = $args['limit'];

        return $wpdb->prepare(
            "SELECT * FROM $table $where_sql ORDER BY start_date $order LIMIT %d",
            $params
        );
    }

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
            echo '<pre class="frcf-debug">';
            echo esc_html( $sql ) . "\n";
            echo sprintf( 'Cursuri găsite: %d', count( $courses ) );
            echo '</pre>';
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
}

Frcf_Courses_Shortcode::register();
