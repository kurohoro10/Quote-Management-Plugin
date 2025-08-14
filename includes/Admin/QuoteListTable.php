<?php
namespace BonzaQuote\Admin;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class QuoteListTable
 *
 * Provides a custom admin table for displaying and managing Bonza Quote submissions.
 * Extends WordPress's built-in WP_List_Table class to support:
 * - Sorting, searching, and pagination of quotes
 * - Custom columns for email, service type, and status
 * - Bulk actions (Approve, Reject, Trash, Restore, Delete Permanently)
 * - Row-specific actions (Edit, Trash, Restore, Delete Permanently)
 *
 * This class is used within the admin dashboard to manage the 'bonza_quote' custom post type.
 *
 * @package BonzaQuote\Admin
 */
class QuoteListTable extends \WP_List_Table {

    /**
     * Stores column headers for the table.
     *
     * @var array
     */
    protected $_column_headers;

    /**
     * Constructor.
     * Sets up the list table properties.
     */
    public function __construct() {
        parent::__construct( [
            'singular' => 'Quote',
            'plural'   => 'Quotes',
            'ajax'     => false,
        ] );
    }

    /**
     * Defines the table columns.
     *
     * @return array
     */
    public function get_columns() {
        return [
            'cb'      => '<input type="checkbox" />',
            'title'   => __( 'Quote Title', 'bonza-quote' ),
            'email'   => __( 'Email', 'bonza-quote' ),
            'service' => __( 'Service Type', 'bonza-quote' ),
            'status'  => __( 'Status', 'bonza-quote' ),
            'date'    => __( 'Date', 'bonza-quote' ),
        ];
    }

    /**
     * Default column rendering.
     *
     * @param object $item Quote post object.
     * @param string $column_name Column key.
     * @return string
     */
    public function column_default( $item, $column_name ) {
        return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '<em>N/A</em>';
    }

    /**
     * Defines sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        return [
            'title' => [ 'title', true ],
            'date'  => [ 'date', false ],
        ];
    }

    /**
     * Gets the column headers for WP_List_Table.
     *
     * @return array
     */
    protected function get_column_info() {
        if ( ! isset( $this->_column_headers ) ) {
            $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
        }
        return $this->_column_headers;
    }

    /**
     * Renders the checkbox column for bulk actions.
     *
     * @param object $item Quote post object.
     * @return string
     */
    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="quote_ids[]" value="%s" />', $item->ID );
    }

    /**
     * Renders the quote title column with row actions.
     *
     * @param object $item Quote post object.
     * @return string
     */
    public function column_title( $item ) {
        $actions = [];

        if ( $item->post_status === 'trash' ) {
            $actions['restore'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( wp_nonce_url( admin_url( 'admin.php?page=bonza-quotes&bq_action=restore&post=' . $item->ID ), 'bq_restore_' . $item->ID ) ),
                __( 'Restore', 'bonza-quote' )
            );
            $actions['delete'] = sprintf(
                '<a href="%s" onclick="return confirm(\' Are you sure you want to permanently delete this quote?\')">%s</a>',
                get_delete_post_link( $item->ID, '', true ),
                __( 'Delete Permanently', 'bonza-quote' )
            );
        } else {
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( get_edit_post_link( $item->ID ) ),
                __( 'Edit', 'bonza-quote' )
            );
            $actions['trash'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( get_delete_post_link( $item->ID ) ),
                __( 'Trash', 'bonza-quote' )
            );
        }

        return sprintf(
            '<a href="%s">%s</a> %s',
            esc_url( get_edit_post_link( $item->ID ) ),
            esc_html( $item->post_title ),
            $this->row_actions( $actions )
        );
    }

    /**
     * Renders the Email column.
     */
    public function column_email( $item ) {
        $email = get_post_meta( $item->ID, 'bq_email', true );
        return $email ? esc_html( $email ) : '<em>No email</em>';
    }

    /**
     * Renders the Service Type column.
     */
    public function column_service( $item ) {
        $service = get_post_meta( $item->ID, 'bq_service', true );
        return $service ? esc_html( $service ) : '<em>No service type</em>';
    }

    /**
     * Renders the Status column with a human-readable label.
     */
    public function column_status( $item ) {
        $map = [
            'publish' => 'Approved',
            'draft'   => 'Rejected',
            'pending' => 'Pending',
            'trash'   => 'Trash'
        ];
        return esc_html( $map[ $item->post_status ] ?? ucfirst( $item->post_status ) );
    }

    /**
     * Renders the Date column.
     */
    public function column_date( $item ) {
        return esc_html( get_the_date( '', $item ) );
    }

    /**
     * Returns the current list view status.
     *
     * @return string
     */
    private function get_view() {
        return $_REQUEST['post_status'] ?? '';
    }

    /**
     * Prepares the items for display.
     * Handles pagination, sorting, and searching.
     */
    public function prepare_items() {
        $per_page     = $this->get_items_per_page( 'quotes_per_page', 10 );
        $current_page = $this->get_pagenum();
        $status       = isset( $_REQUEST['post_status'] ) ? sanitize_key( $_REQUEST['post_status'] ) : '';
        $search       = ! empty( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';

        $valid_statuses = [ 'publish', 'draft', 'pending', 'trash' ];

        $args = [
            'post_type'      => 'bonza_quote',
            'post_status'    => in_array( $status, $valid_statuses, true ) ? $status : [ 'publish', 'draft', 'pending' ],
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
        ];

        if ( $search ) {
            $args['s'] = $search;
        }
        if ( ! empty( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], [ 'title', 'date' ], true ) ) {
            $args['orderby'] = $_REQUEST['orderby'];
        }
        if ( ! empty( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], [ 'asc', 'desc' ], true ) ) {
            $args['order'] = $_REQUEST['order'];
        }

        $query       = new \WP_Query( $args );
        $this->items = $query->posts;

        $this->set_pagination_args( [
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => $query->max_num_pages,
        ] );
    }

    /**
     * Defines the filter views (All, Approved, Rejected, Pending, Trash).
     */
    public function get_views() {
        $views   = [];
        $counts  = wp_count_posts( 'bonza_quote' );
        $current = $_REQUEST['post_status'] ?? '';

        $total = ( $counts->publish ?? 0 ) + ( $counts->draft ?? 0 ) + ( $counts->pending ?? 0 );
        $views['all'] = sprintf(
            "<a href='%s' class='%s'>All <span class='count'>(%d)</span></a>",
            esc_url( remove_query_arg( 'post_status', admin_url( 'admin.php?page=bonza-quotes' ) ) ),
            $current === 'all' ? 'current' : '',
            $total
        );

        $statuses = [
            'publish'  => 'Approved',
            'draft'    => 'Rejected',
            'pending'  => 'Pending',
            'trash'    => 'Trash',
        ];

        foreach ( $statuses as $status => $label ) {
            $count = $counts->$status ?? 0;
            if ( $count > 0 ) {
                $views[$status] = sprintf(
                    "<a href='%s' class='%s'>%s <span class='count'>(%d)</span></a>",
                    esc_url( add_query_arg( 'post_status', $status, admin_url( 'admin.php?page=bonza-quotes' ) ) ),
                    $current === $status ? 'current' : '',
                    $label,
                    $count
                );
            }
        }

        return $views;
    }

    /**
     * Defines available bulk actions.
     *
     * @return array
     */
    public function get_bulk_actions() {
        if ( $this->get_view() === 'trash' ) {
            return [
                'restore' => 'Restore',
                'delete'  => 'Delete Permanently',
            ];
        }
        return [
            'approve' => 'Approve',
            'reject'  => 'Reject',
            'trash'   => 'Move to Trash',
        ];
    }

    /**
     * Processes bulk actions (Approve, Reject, Trash, Restore, Delete).
     */
    public function process_bulk_action() {
        if ( isset( $_POST['quote_ids'], $_POST['_wpnonce'] ) && check_admin_referer( 'bulk-quotes' ) ) {
            $action = $this->current_action();
            $ids    = array_map( 'absint', $_POST['quote_ids'] );

            foreach ( $ids as $id ) {
                switch ( $action ) {
                    case 'approve':
                        wp_update_post( [ 'ID' => $id, 'post_status' => 'publish' ] );
                        break;
                    case 'reject':
                        wp_update_post( [ 'ID' => $id, 'post_status' => 'draft' ] );
                        break;
                    case 'trash':
                        wp_trash_post( $id );
                        break;
                    case 'restore':
                        wp_untrash_post( $id );
                        wp_update_post( [ 'ID' => $id, 'post_status' => 'pending' ] );
                        break;
                    case 'delete':
                        wp_delete_post( $id, true );
                        break;
                }
            }
            wp_redirect( admin_url( 'admin.php?page=bonza-quotes&post_status=' . ( $action === 'delete' ? 'trash' : '' ) ) );
            exit;
        }
    }
}
