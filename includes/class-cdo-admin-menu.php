<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Custom Data Organizer
 *
 * - Adds a "Custom Data" top-level menu.
 * - Lets you choose which custom post types to group under it.
 * - Selected CPTs are removed from the main admin menu and only shown under "Custom Data":
 *
 *   Custom Data
 *      Accommodations
 *          – Add Accommodations
 *          – Taxonomies
 *      Destinations
 *          – Add Destinations
 *          – Taxonomies
 *      ...
 */
class CDO_Admin_Menu {

    const OPTION_KEY  = 'cdo_managed_post_types';
    const PARENT_SLUG = 'cdo-main';

    /**
     * Core post types we never touch.
     * (Safety list even though we only manage custom CPTs.)
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
        add_filter( 'register_post_type_args', [ $this, 'filter_post_type_args' ], 20, 2 );
    }

    /**
     * Get the list of CPT slugs that should be grouped under "Custom Data".
     *
     * @return string[]
     */
    public static function get_managed_post_types() : array {
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
     * - top-level "Custom Data" menu
     * - Overview page
     * - Settings page
     * - one group of submenus per managed CPT
     */
    public function register_menus() {

        // Parent "Custom Data" menu.
        add_menu_page(
            __( 'Custom Data', 'custom-data-organizer' ),
            __( 'Custom Data', 'custom-data-organizer' ),
            'manage_options',
            self::PARENT_SLUG,
            [ $this, 'render_overview_page' ],
            'dashicons-index-card',
            30
        );

        // Overview submenu (same slug as parent).
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Overview', 'custom-data-organizer' ),
            __( 'Overview', 'custom-data-organizer' ),
            'manage_options',
            self::PARENT_SLUG,
            [ $this, 'render_overview_page' ]
        );

        // Settings page.
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Custom Data Organizer Settings', 'custom-data-organizer' ),
            __( 'Settings', 'custom-data-organizer' ),
            'manage_options',
            'cdo-settings',
            [ $this, 'render_settings_page' ]
        );

        // Now add submenus for each CPT that has been selected.
        $managed = self::get_managed_post_types();

        if ( empty( $managed ) ) {
            return;
        }

        foreach ( $managed as $post_type ) {
            $pt_obj = get_post_type_object( $post_type );
            if ( ! $pt_obj ) {
                continue;
            }

            // Skip core types for safety.
            if ( in_array( $post_type, $this->core_post_types, true ) ) {
                continue;
            }

            $this->add_cpt_group( $pt_obj );
        }
    }

    /**
     * For a single CPT, add:
     *  - {Menu Name}          → View all
     *  - — Add {Menu Name}    → Add new
     *  - — Taxonomies         → First taxonomy, if present
     *
     * @param WP_Post_Type $pt_obj
     */
    protected function add_cpt_group( $pt_obj ) {

        $slug       = $pt_obj->name;
        $plural     = $pt_obj->labels->menu_name ?: $pt_obj->labels->name ?: ucfirst( $slug );
        $cap_edit   = $pt_obj->cap->edit_posts ?? 'edit_posts';
        $cap_create = $pt_obj->cap->create_posts ?? $cap_edit;

        // 1) View all – main entry for this CPT.
        add_submenu_page(
            self::PARENT_SLUG,
            sprintf( __( 'View All %s', 'custom-data-organizer' ), $plural ),
            $plural,
            $cap_edit,
            'edit.php?post_type=' . $slug
        );

        // 2) Add new – visually indented using "— ".
        add_submenu_page(
            self::PARENT_SLUG,
            sprintf( __( 'Add %s', 'custom-data-organizer' ), $plural ),
            '— ' . sprintf( __( 'Add %s', 'custom-data-organizer' ), $plural ),
            $cap_create,
            'post-new.php?post_type=' . $slug
        );

        // 3) Taxonomies – first taxonomy associated with this CPT (if any).
        $taxonomies = get_object_taxonomies( $slug, 'objects' );

        if ( ! empty( $taxonomies ) ) {
            $taxonomy = reset( $taxonomies );

            add_submenu_page(
                self::PARENT_SLUG,
                sprintf( __( '%s Taxonomies', 'custom-data-organizer' ), $plural ),
                '— ' . __( 'Categories', 'custom-data-organizer' ),
                'manage_categories',
                'edit-tags.php?taxonomy=' . $taxonomy->name . '&post_type=' . $slug
            );
        }
    }

    /**
     * Ensure selected CPTs do NOT appear as top-level menus,
     * but only under our "Custom Data" parent.
     */
    public function filter_post_type_args( array $args, string $post_type ) : array {

        $managed = self::get_managed_post_types();

        if ( ! in_array( $post_type, $managed, true ) ) {
            // Not one of the CPTs we've chosen -> leave it alone.
            return $args;
        }

        // Do not interfere with built-in types.
        if ( ! empty( $args['_builtin'] ) ) {
            return $args;
        }

        // If some plugin explicitly hides this CPT, respect that.
        if ( isset( $args['show_in_menu'] ) && $args['show_in_menu'] === false ) {
            return $args;
        }

        /**
         * Any CPT we are managing should show under our menu slug,
         * not as its own top-level menu.
         */
        $args['show_in_menu'] = self::PARENT_SLUG;

        return $args;
    }

    /**
     * Overview page under "Custom Data".
     */
    public function render_overview_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Custom Data Organizer', 'custom-data-organizer' ); ?></h1>
            <p><?php esc_html_e( 'This menu groups selected custom post types into a single, tidy "Custom Data" menu.', 'custom-data-organizer' ); ?></p>
            <p>
                <?php
                printf(
                    esc_html__( 'To choose which post types appear here, go to %sSettings → Custom Data Organizer%s.', 'custom-data-organizer' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=cdo-settings' ) ) . '">',
                    '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Settings page: choose which CPTs to manage.
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