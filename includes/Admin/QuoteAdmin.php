<?php
namespace BonzaQuote\Admin;

/**
 * Class QuoteAdmin
 *
 * Manages the admin interface for the Bonza Quote Management plugin.
 * Responsibilities include:
 * - Registering the "Bonza Quotes" admin menu.
 * - Displaying and managing quotes through a list table.
 * - Handling quote restoration actions from the trash.
 *
 * @package BonzaQuote\Admin
 */
class QuoteAdmin {

    /**
     * Initialize hooks for admin menu, screen options, and restore actions.
     *
     * @return void
     */
    public function init() {
        // Add admin menu for quote management.
        add_action( 'admin_menu', [ $this, 'add_menu' ] );

        // Handle custom screen option for quotes per page.
        add_filter( 'set-screen-option', function( $status, $option, $value ) {
            return ( $option === 'quotes_per_page' ) ? $value : $status;
        }, 10, 3 );

        // Restore a trashed quote back to pending status when requested.
        add_action( 'admin_init', function() {
            if ( isset( $_GET['bq_action'], $_GET['post'] ) && $_GET['bq_action'] === 'restore' ) {
                $post_id = absint( $_GET['post'] );

                // Permission check.
                if ( ! current_user_can( 'edit_post', $post_id ) ) {
                    wp_die( __( 'You do not have permission to restore this quote.', 'bonza-quote' ) );
                }

                // Nonce verification for security.
                if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'bq_restore_' . $post_id ) ) {
                    wp_die( __( 'Security check failed.', 'bonza-quote' ) );
                }

                // Restore the post and set its status back to pending.
                wp_untrash_post( $post_id );
                wp_update_post( [
                    'ID'          => $post_id,
                    'post_status' => 'pending',
                ] );

                // Redirect to pending quotes view with restored flag.
                wp_redirect( admin_url( 'admin.php?page=bonza-quotes&post_status=pending&restored=1' ) );
                exit;
            }
        } );
    }

    /**
     * Register the Bonza Quotes admin menu page and its hooks.
     *
     * @return void
     */
    public function add_menu() {
        $hook = add_menu_page(
            __( 'Bonza Quotes', 'bonza-quote' ),
            __( 'Bonza Quotes', 'bonza-quote' ),
            'manage_options',
            'bonza-quotes',
            [ $this, 'render_quotes_page' ],
            'dashicons-feedback',
            26
        );

        // Set up screen options for quotes list table.
        add_action( "load-$hook", [ $this, 'screen_option' ] );

        // Process bulk actions when the page is loaded.
        add_action( "load-$hook", function() {
            $list_table = new QuoteListTable();
            $list_table->process_bulk_action();
        } );
    }

    /**
     * Configure screen options for the quotes list table.
     *
     * @return void
     */
    public function screen_option() {
        add_screen_option(
            'per_page',
            [
                'label'   => __( 'Quotes per page', 'bonza-quote' ),
                'default' => 10,
                'option'  => 'quotes_per_page',
            ]
        );
    }

    /**
     * Render the Bonza Quotes admin page with the list table and controls.
     *
     * @return void
     */
    public function render_quotes_page() {
        $list_table = new QuoteListTable();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Bonza Quotes', 'bonza-quote' ); ?></h1>

            <form method="post" action="">
                <?php 
                    $list_table->views();
                    echo '<input type="hidden" name="page" value="bonza-quotes" />';
                    wp_nonce_field( 'bulk-quotes' );
                    $list_table->search_box( __( 'Search Quotes', 'bonza-quote' ), 'search_quotes' );
                    $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }
}
