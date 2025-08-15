<?php
namespace BonzaQuote\Frontend;

/**
 * QuoteForm Class
 *
 * Handles rendering and AJAX processing of the Bonza Quote frontend submission form.
 * Features:
 * - Accessible form markup following WCAG guidelines.
 * - AJAX-based submission with proper nonce verification.
 * - Language translation ready via WordPress i18n functions.
 * - Sanitization and validation of user input.
 * - Automatic email notification to admin upon submission.
 *
 * @package BonzaQuote\Frontend
 */
class QuoteForm {

    /**
     * Initialize hooks and shortcodes.
     *
     * @return void
     */
    public function init() {
        add_shortcode( 'bonza_quote_form', [ $this, 'render_form' ] );

        // AJAX actions for both logged-in and guest users
        add_action( 'wp_ajax_bq_submit_quote', [ $this, 'handle_ajax_submission' ] );
        add_action( 'wp_ajax_nopriv_bq_submit_quote', [ $this, 'handle_ajax_submission' ] );
    }

    /**
     * Render the quote submission form.
     *
     * Includes accessible markup, validation attributes,
     * nonce protection, and theme-ready styles.
     *
     * @return string HTML output for the form.
     */
    public function render_form() {
        ob_start();
        ?>
        <form id="bonza-quote-form" method="post" role="form" aria-labelledby="bq_form_title" novalidate>
            <h2 id="bq_form_title" class="screen-reader-text">
                <?php esc_html_e( 'Quote Request Form', 'bonza-quote' ); ?>
            </h2>

            <p>
                <label for="bq_name">
                    <?php esc_html_e( 'Name', 'bonza-quote' ); ?>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="text" id="bq_name" name="bq_name" required aria-required="true" autocomplete="name" />
            </p>

            <p>
                <label for="bq_email">
                    <?php esc_html_e( 'Email', 'bonza-quote' ); ?>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="email" id="bq_email" name="bq_email" required aria-required="true" autocomplete="email" />
            </p>

            <p>
                <label for="bq_service">
                    <?php esc_html_e( 'Service Type', 'bonza-quote' ); ?>
                    <span class="required" aria-hidden="true">*</span>
                </label>
                <input type="text" id="bq_service" name="bq_service" required aria-required="true" />
            </p>

            <p>
                <label for="bq_notes">
                    <?php esc_html_e( 'Notes', 'bonza-quote' ); ?>
                </label>
                <textarea id="bq_notes" name="bq_notes"></textarea>
            </p>

            <?php wp_nonce_field( 'bq_ajax_form', 'bq_nonce' ); ?>

            <p>
                <button type="submit" class="bq-submit-btn" aria-label="<?php esc_attr_e( 'Submit your quote request', 'bonza-quote' ); ?>">
                    <span class="bq-btn-text"><?php esc_html_e( 'Submit Quote', 'bonza-quote' ); ?></span>
                    <span class="bq-btn-spinner" aria-hidden="true"></span>
                </button>
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX form submission.
     *
     * Validates and sanitizes form inputs, saves the quote as a pending custom post type,
     * sends an admin notification, and returns a JSON response.
     *
     * @return void
     */
    public function handle_ajax_submission() {
        check_ajax_referer( 'bq_ajax_form', 'bq_nonce' );

        $name    = sanitize_text_field( wp_unslash( $_POST['bq_name'] ?? '' ) );
        $email   = sanitize_email( wp_unslash( $_POST['bq_email'] ?? '' ) );
        $service = sanitize_text_field( wp_unslash( $_POST['bq_service'] ?? '' ) );
        $notes   = sanitize_textarea_field( wp_unslash( $_POST['bq_notes'] ?? '' ) );

        // Validate required fields
        if ( empty( $name ) || empty( $email ) || empty( $service ) ) {
            wp_send_json_error( [
                'message' => __( 'Please fill in all required fields.', 'bonza-quote' ),
            ] );
        }

        // Save as a custom post type entry
        wp_insert_post( [
            'post_type'    => 'bonza_quote',
            'post_status'  => 'pending',
            'post_title'   => $name,
            'post_content' => $notes,
            'meta_input'   => [
                'bq_email'   => $email,
                'bq_service' => $service,
            ],
        ] );

        // Send notification email to admin
        if ( class_exists( 'BonzaQuote\\Frontend\\QuoteMailer' ) ) {
            $mailer = new QuoteMailer();
            $mailer->send_admin_notification( $name, $email, $service, $notes );
        }

        wp_send_json_success( [
            'message' => __( 'Thank you! Your quote has been submitted.', 'bonza-quote' ),
        ] );
    }
}