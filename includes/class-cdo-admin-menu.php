<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Custom Data Organizer
 *
 * - Adds a "Custom Data" top-level menu.
 * - Lets you choose which CPTs to manage.
 * - Hides those CPTs' original top-level menu entries.
 * - Shows, for each selected CPT:
 *
 *      All {Plural}
 *      Add {Singular}
 *      Categories
 *
 *   with a thin gray separator between groups.
 * - Provides a "Data Types" page with a table of actions.
 */
class CDO_Admin_Menu {

    const OPTION_KEY  = 'cdo_managed_post_types';
    const PARENT_SLUG = 'cdo-main';

    /**
     * Core post types we never touch (safety).
     */
    protected $core_post_types = [
        'post',
        'page',
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_navigation',
    ];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ], 20 );
        add_action( 'admin_menu', [ $this, 'hide_original_cpt_menus' ], 999 );
        add_action( 'admin_head', [ $this, 'output_admin_css' ] );
    }

    /**
     * Get CPT slugs that should be grouped under "Custom Data".
     *
     * @return string[]
     */
    public static function get_managed_post_types(): array {
        $managed = get_option( self::OPTION_KEY, [] );

        if ( ! is_array( $managed ) ) {
            $managed = [];
        }

        $managed = array_map( 'sanitize_key', $managed );
        $managed = array_unique( $managed );

        return $managed;
    }

    /**
     * Register:
     *  - "Custom Data" top-level menu
     *  - Overview
     *  - Data Types
     *  - Settings
     *  - Grouped items for each selected CPT:
     *      All {Plural}
     *      Add {Singular}
     *      Categories
     *    plus separator items.
     */
    public function register_menus() {

        // Parent menu.
        add_menu_page(
            __( 'Custom Data', 'custom-data-organizer' ),
            __( 'Custom Data', 'custom-data-organizer' ),
            'manage_options',
            self::PARENT_SLUG,
            [ $this, 'render_overview_page' ],
            'dashicons-index-card',
            30
        );

        // Overview.
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Overview', 'custom-data-organizer' ),
            __( 'Overview', 'custom-data-organizer' ),
            'manage_options',
            self::PARENT_SLUG,
            [ $this, 'render_overview_page' ]
        );

        // Data Types (nice table view).
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Data Types', 'custom-data-organizer' ),
            __( 'Data Types', 'custom-data-organizer' ),
            'manage_options',
            'cdo-data-types',
            [ $this, 'render_data_types_page' ]
        );

        // Settings.
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Custom Data Organizer Settings', 'custom-data-organizer' ),
            __( 'Settings', 'custom-data-organizer' ),
            'manage_options',
            'cdo-settings',
            [ $this, 'render_settings_page' ]
        );

        // Now build dynamic CPT groups.
        $managed = self::get_managed_post_types();

        if ( empty( $managed ) ) {
            return;
        }

        foreach ( $managed as $post_type ) {
            $pt_obj = get_post_type_object( $post_type );
            if ( ! $pt_obj ) {
                continue;
            }

            if ( in_array( $post_type, $this->core_post_types, true ) ) {
                continue;
            }

            $this->add_cpt_group( $pt_obj );
        }
    }

    /**
     * For a single CPT, add:
     *
     *  (separator)
     *  All {Plural}
     *  Add {Singular}
     *  Categories
     *
     * No "—" indent; flat layout.
     *
     * @param WP_Post_Type $pt_obj
     */
    protected function add_cpt_group( $pt_obj ) {

        $slug       = $pt_obj->name;
        $plural     = $pt_obj->labels->menu_name ?: $pt_obj->labels->name ?: ucfirst( $slug );
        $singular   = $pt_obj->labels->singular_name ?: rtrim( $plural, 's' );
        $cap_edit   = $pt_obj->cap->edit_posts ?? 'edit_posts';
        $cap_create = $pt_obj->cap->create_posts ?? $cap_edit;

        // Separator (we'll style this as a thin gray line).
        add_submenu_page(
            self::PARENT_SLUG,
            '',
            '────────────',
            'manage_options',
            'cdo-separator-' . $slug,
            '__return_null'
        );

        // All {Plural}.
        add_submenu_page(
            self::PARENT_SLUG,
            sprintf( __( 'All %s', 'custom-data-organizer' ), $plural ),
            sprintf( __( 'All %s', 'custom-data-organizer' ), $plural ),
            $cap_edit,
            'edit.php?post_type=' . $slug
        );

        // Add {Singular}.
        add_submenu_page(
            self::PARENT_SLUG,
            sprintf( __( 'Add %s', 'custom-data-organizer' ), $singular ),
            sprintf( __( 'Add %s', 'custom-data-organizer' ), $singular ),
            $cap_create,
            'post-new.php?post_type=' . $slug
        );

        // Categories (first taxonomy, if present).
        $taxonomies = get_object_taxonomies( $slug, 'objects' );

        if ( ! empty( $taxonomies ) ) {
            $taxonomy = reset( $taxonomies );

            add_submenu_page(
                self::PARENT_SLUG,
                sprintf( __( '%s Categories', 'custom-data-organizer' ), $plural ),
                __( 'Categories', 'custom-data-organizer' ),
                'manage_categories',
                'edit-tags.php?taxonomy=' . $taxonomy->name . '&post_type=' . $slug
            );
        }
    }

    /**
     * Hide original top-level menu entries for managed CPTs.
     */
    public function hide_original_cpt_menus() {

        $managed = self::get_managed_post_types();

        if ( empty( $managed ) ) {
            return;
        }

        foreach ( $managed as $post_type ) {
            $pt_obj = get_post_type_object( $post_type );
            if ( ! $pt_obj ) {
                continue;
            }

            if ( in_array( $post_type, $this->core_post_types, true ) ) {
                continue;
            }

            // Default CPT menu slug.
            $menu_slug = 'edit.php?post_type=' . $post_type;

            remove_menu_page( $menu_slug );
        }
    }

    /**
     * Admin CSS to turn our "separator" submenu entries into thin gray lines.
     */
    public function output_admin_css() {
        ?>
        <style>
            /* Style separators inside the Custom Data submenu */
            #adminmenu .toplevel_page_<?php echo esc_js( self::PARENT_SLUG ); ?> .wp-submenu a[href*="cdo-separator-"] {
                /* Make the link itself invisible but keep the li for layout */
                font-size: 0;
                line-height: 0;
                padding-top: 0;
                padding-bottom: 0;
                padding-left: 0;
                padding-right: 0;
                pointer-events: none;
            }

            #adminmenu .toplevel_page_<?php echo esc_js( self::PARENT_SLUG ); ?> .wp-submenu a[href*="cdo-separator-"]::before {
                content: "";
                display: block;
                border-top: 1px solid #dcdcde; /* Thin gray line (style B) */
                margin: 6px 0;
                width: 100%;
            }
        </style>
        <?php
    }

    /**
     * Overview page.
     */
    public function render_overview_page() {
        $overview_message = __( 'This plugin groups selected custom post types under a single "Custom Data" menu for a cleaner admin experience.', 'custom-data-organizer' );
        $overview_cta      = __( 'Use the %1$sData Types%2$s page to see a list of your custom data types, or adjust which types appear here in %3$sSettings%4$s.', 'custom-data-organizer' );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Custom Data Organizer', 'custom-data-organizer' ); ?></h1>
            <p><?php echo esc_html( $overview_message ); ?></p>
            <p>
                <?php
                printf(
                    esc_html( $overview_cta ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=cdo-data-types' ) ) . '">',
                    '</a>',
                    '<a href="' . esc_url( admin_url( 'admin.php?page=cdo-settings' ) ) . '">',
                    '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * "Data Types" page: nice table of managed CPTs with action links.
     */
    public function render_data_types_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $managed          = self::get_managed_post_types();
        $data_types_intro = __( 'These are the custom post types currently grouped under the "Custom Data" menu.', 'custom-data-organizer' );
        $no_data_types    = __( 'No data types selected yet. Go to Settings and choose which post types to manage.', 'custom-data-organizer' );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Custom Data Types', 'custom-data-organizer' ); ?></h1>
            <p><?php echo esc_html( $data_types_intro ); ?></p>

            <?php if ( empty( $managed ) ) : ?>
                <p><?php echo esc_html( $no_data_types ); ?></p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Data Type', 'custom-data-organizer' ); ?></th>
                            <th><?php esc_html_e( 'Post Type Slug', 'custom-data-organizer' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'custom-data-organizer' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $managed as $post_type ) : ?>
                        <?php
                        $pt_obj = get_post_type_object( $post_type );
                        if ( ! $pt_obj ) {
                            continue;
                        }

                        $plural   = $pt_obj->labels->menu_name ?: $pt_obj->labels->name ?: ucfirst( $post_type );
                        $singular = $pt_obj->labels->singular_name ?: rtrim( $plural, 's' );

                        $view_url = admin_url( 'edit.php?post_type=' . $post_type );
                        $add_url  = admin_url( 'post-new.php?post_type=' . $post_type );

                        $taxonomies = get_object_taxonomies( $post_type, 'objects' );
                        $cat_url    = '';

                        if ( ! empty( $taxonomies ) ) {
                            $taxonomy = reset( $taxonomies );
                            $cat_url  = admin_url( 'edit-tags.php?taxonomy=' . $taxonomy->name . '&post_type=' . $post_type );
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html( $plural ); ?></td>
                            <td><code><?php echo esc_html( $post_type ); ?></code></td>
                            <td>
                                <a href="<?php echo esc_url( $view_url ); ?>">
                                    <?php printf( esc_html__( 'All %s', 'custom-data-organizer' ), esc_html( $plural ) ); ?>
                                </a>
                                &nbsp;|&nbsp;
                                <a href="<?php echo esc_url( $add_url ); ?>">
                                    <?php printf( esc_html__( 'Add %s', 'custom-data-organizer' ), esc_html( $singular ) ); ?>
                                </a>
                                <?php if ( $cat_url ) : ?>
                                    &nbsp;|&nbsp;
                                    <a href="<?php echo esc_url( $cat_url ); ?>">
                                        <?php esc_html_e( 'Categories', 'custom-data-organizer' ); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Settings page: tick which CPTs to manage.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle save.
        if ( isset( $_POST['cdo_nonce'] ) && wp_verify_nonce( $_POST['cdo_nonce'], 'cdo_save_settings' ) ) {
            $selected = isset( $_POST['cdo_post_types'] ) ? (array) $_POST['cdo_post_types'] : [];
            $selected = array_map( 'sanitize_key', $selected );
            update_option( self::OPTION_KEY, $selected );

            echo '<div class="updated notice"><p>' . esc_html__( 'Settings saved.', 'custom-data-organizer' ) . '</p></div>';
        }

        // Candidate CPTs: public, non-builtin.
        $post_types = get_post_types(
            [
                'public'   => true,
                '_builtin' => false,
            ],
            'objects'
        );

        $managed = self::get_managed_post_types();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Custom Data Organizer Settings', 'custom-data-organizer' ); ?></h1>
            <p><?php esc_html_e( 'Select the custom post types you want grouped under the "Custom Data" menu.', 'custom-data-organizer' ); ?></p>

            <form method="post">
                <?php wp_nonce_field( 'cdo_save_settings', 'cdo_nonce' ); ?>

                <?php if ( empty( $post_types ) ) : ?>
                    <p><?php esc_html_e( 'No custom post types found.', 'custom-data-organizer' ); ?></p>
                <?php else : ?>
                    <table class="form-table">
                        <tbody>
                        <?php foreach ( $post_types as $slug => $pt_obj ) : ?>
                            <tr>
                                <th scope="row">
                                    <?php echo esc_html( $pt_obj->labels->name ); ?>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="cdo_post_types[]"
                                               value="<?php echo esc_attr( $slug ); ?>"
                                            <?php checked( in_array( $slug, $managed, true ) ); ?>
                                        />
                                        <code><?php echo esc_html( $slug ); ?></code>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}