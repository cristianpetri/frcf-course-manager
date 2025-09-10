<?php
if (!defined('ABSPATH')) { exit; }

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
