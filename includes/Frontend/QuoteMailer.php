<?php
namespace BonzaQuote\Frontend;

/**
 * Class QuoteMailer
 *
 * Handles sending admin notification emails for quote requests.
 * Emails are sent as HTML with a plain-text fallback for compatibility.
 */
class QuoteMailer {

    /**
     * Sends an email to the site administrator with quote request details.
     *
     * @param string $name    Name of the requester.
     * @param string $email   Email address of the requester.
     * @param string $service Requested service type.
     * @param string $notes   Additional notes from the requester (optional).
     *
     * @return bool True if the email(s) were sent successfully, false otherwise.
     */
    public function send_admin_notification( $name, $email, $service, $notes = '' ) {
        $admin_email = get_option( 'admin_email' );

        // Sanitize inputs
        $name    = sanitize_text_field( $name );
        $email   = sanitize_email( $email );
        $service = sanitize_text_field( $service );
        $notes   = sanitize_textarea_field( $notes ?: '(No notes provided)' );

        $subject    = sprintf( 'New Quote Request from %s', $name );

        // Plain text fallback
        $plain_body = sprintf(
            "A new quote request has been submitted on your website:\n\n" .
            "Name: %s\n" .
            "Email: %s\n" .
            "Service Type: %s\n" .
            "Notes:\n%s",
            $name,
            $email,
            $service,
            $notes
        );

        // HTML version
        $html_body = '
            <!DOCTYPE html>
            <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>New Quote Request</title>
                </head>
                <body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Arial, sans-serif;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4; padding:20px;">
                        <tr>
                            <td align="center">
                                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden;">
                                    <tr>
                                        <td style="background-color:#2b5797; color:#ffffff; padding:20px; text-align:center;">
                                            <h1 style="margin:0; font-size:22px; font-weight:bold;">New Quote Request</h1>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:20px; color:#333333; font-size:16px; line-height:1.5;">
                                            <p><strong>Name:</strong> ' . esc_html( $name ) . '</p>
                                            <p><strong>Email:</strong> <a href="mailto:' . esc_html( $email ) . '" style="color:#2b5797;">' . esc_html( $email ) . '</a></p>
                                            <p><strong>Service Type:</strong> ' . esc_html( $service ) . '</p>
                                            <p><strong>Notes:</strong><br>' . nl2br( esc_html( $notes ) ) . '</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="background-color:#f4f4f4; color:#555555; padding:15px; font-size:12px; text-align:center;">
                                            This email was sent from the Bonza Quote Management plugin.
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
            </html>';

        // Set HTML content type temporarily
        $set_html_content_type = function() { return 'text/html'; };
        add_filter( 'wp_mail_content_type', $set_html_content_type );

        // Send HTML email
        $html_result = wp_mail( $admin_email, $subject, $html_body );

        // Restore default content type
        remove_filter( 'wp_mail_content_type', $set_html_content_type );

        // Optionally send plain text version (helps in testing environments like Mailtrap)
        $plain_result = wp_mail( $admin_email, $subject, $plain_body );

        return $html_result && $plain_result;
    }
}
