<?php
namespace BonzaQuote\PostTypes;

/**
 * Class QuotePostType
 *
 * Registers the `bonza_quote` custom post type used to store submitted quotes.
 * The post type is not publicly queryable but is accessible in the admin area
 * for internal review and processing of quote requests.
 */
class QuotePostType {

    /**
     * Attaches the custom post type registration to the 'init' action.
     *
     * @return void
     */
    public function register() {
        add_action( 'init', [ $this, 'register_post_type' ] );
    }

    /**
     * Registers the `bonza_quote` custom post type.
     *
     * - Not publicly visible on the frontend.
     * - Accessible in the admin dashboard (UI enabled but hidden from main menu).
     * - Supports title and editor fields for storing request details.
     *
     * @return void
     */
    public function register_post_type() {
        register_post_type( 'bonza_quote', [
            'labels' => [
                'name'          => __( 'Quotes', 'bonza-quote' ),
                'singular_name' => __( 'Quote', 'bonza-quote' ),
            ],
            'public'              => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'supports'            => [ 'title', 'editor' ],
            'capability_type'     => 'post',
        ] );
    }
}
