<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frcf_Courses_Category_Shortcode {
    public static function register() {
        add_shortcode( 'frcf_courses_by_category', array( __CLASS__, 'output' ) );
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
            $where_conditions[] = 'GREATEST(start_date, IFNULL(NULLIF(end_date, "0000-00-00"), start_date)) >= %s';
            $prepare_values[]   = $today;
        }

        if ( ! empty( $where_conditions ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where_conditions );
        }

        $sql .= ' ORDER BY category ASC, start_date ASC';

        $sql            .= ' LIMIT %d';
        $prepare_values[] = $args['limit'];

        if ( ! empty( $prepare_values ) ) {
            return $wpdb->prepare( $sql, $prepare_values );
        }

        return $sql;
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
            ),
            $atts,
            'frcf_courses_by_category'
        );

        $args = array(
            'columns'  => max( 2, min( 4, (int) $atts['columns'] ) ),
            'location' => sanitize_text_field( $atts['location'] ),
            'limit'    => max( 1, (int) $atts['limit'] ),
            'show_all' => in_array( strtolower( $atts['show_all'] ), array( '1', 'true', 'yes' ), true ),
        );

        $sql     = self::build_query( $args );
        $courses = $wpdb->get_results( $sql );

        if ( ! $courses ) {
            return '<div class="frcf-no-courses"><p>' . esc_html__( 'Nu există cursuri disponibile în acest moment.', 'frcf-courses' ) . '</p></div>';
        }

        $grouped = array();
        foreach ( $courses as $course ) {
            $category = $course->category ? $course->category : esc_html__( 'Fără categorie', 'frcf-courses' );
            if ( ! isset( $grouped[ $category ] ) ) {
                $grouped[ $category ] = array();
            }
            $grouped[ $category ][] = $course;
        }

        ob_start();
        echo '<div class="frcf-courses-by-category">';
        foreach ( $grouped as $category => $items ) {
            echo '<h2 class="frcf-category-title">' . esc_html( $category ) . '</h2>';
            echo '<div class="frcf-courses-grid columns-' . esc_attr( $args['columns'] ) . '">';
            foreach ( $items as $course ) {
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
                            <a href="https://cursuri.frcf.ro/" class="frcf-btn-register"><?php echo esc_html__( 'Înscrie-te acum!', 'frcf-courses' ); ?></a>
                        </div>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
        }
        echo '</div>';

        return ob_get_clean();
    }
}

Frcf_Courses_Category_Shortcode::register();
