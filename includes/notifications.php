<?php
/**
 * Secure Blog CMS - Notifications Class
 * Handles sending email notifications
 */

if (!defined('SECURE_CMS_INIT')) {
    die('Direct access not permitted');
}

class Notifications
{
    private static $instance = null;
    private $security;

    /**
     * Singleton pattern
     * @return Notifications
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->security = Security::getInstance();
    }

    /**
     * A helper function to send emails.
     * In a real application, this would use a library like PHPMailer.
     *
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param string $headers
     * @return bool
     */
    private function sendEmail($to, $subject, $message, $headers)
    {
        // For development, we'll log emails instead of sending them.
        // To send real emails, you would replace this with mail() or a library.
        $logMessage = "---- EMAIL ----\n" .
                      "To: {$to}\n" .
                      "Subject: {$subject}\n" .
                      "Headers: {$headers}\n" .
                      "Message:\n{$message}\n" .
                      "---------------\n";

        error_log($logMessage, 3, LOGS_DIR . '/emails.log');

        // To actually send mail (requires server configuration):
        // return mail($to, $subject, $message, $headers);

        return true; // Assume success for logging
    }

    /**
     * Sends a notification to the admin about a new comment awaiting moderation.
     *
     * @param array $post The post that was commented on.
     * @param array $comment The new comment data.
     * @return bool
     */
    public function sendNewCommentNotification($post, $comment)
    {
        // You would set the admin email in your config.php
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@example.com';
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Secure Blog CMS';

        $subject = "[{$siteName}] New Comment Awaiting Moderation on \"{$post['title']}\"";

        $postUrl = SITE_URL . '/post.php?slug=' . $post['slug'];
        // In a real app, you'd have a dedicated admin page to manage comments
        $moderationUrl = SITE_URL . '/admin/comments.php';

        $message = "A new comment has been posted on your article: \"{$post['title']}\".\n\n";
        $message .= "Author: " . $comment['author_name'] . "\n";
        $message .= "Comment:\n" . $comment['content'] . "\n\n";
        $message .= "You can moderate this comment here:\n";
        $message .= $moderationUrl . "\n\n";
        $message .= "View the post here:\n";
        $message .= $postUrl . "\n";

        $headers = "From: no-reply@" . parse_url(SITE_URL, PHP_URL_HOST) . "\r\n" .
                   "Reply-To: " . $adminEmail . "\r\n" .
                   "X-Mailer: PHP/" . phpversion();

        return $this->sendEmail($adminEmail, $subject, $message, $headers);
    }

    /**
     * Sends a password reset email to a user.
     *
     * @param array $user The user data.
     * @param string $resetToken The password reset token.
     * @return bool
     */
    public function sendPasswordResetNotification($user, $resetToken)
    {
        $userEmail = $user['email'] ?? null;
        if (!$userEmail) {
            return false;
        }

        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Secure Blog CMS';
        $subject = "[{$siteName}] Password Reset Request";
        $resetLink = SITE_URL . '/reset-password.php?token=' . urlencode($resetToken);

        $message = "Hello " . $user['username'] . ",\n\n";
        $message .= "A password reset was requested for your account on {$siteName}.\n";
        $message .= "If you did not request this, you can safely ignore this email.\n\n";
        $message .= "To reset your password, click the following link:\n";
        $message .= $resetLink . "\n\n";
        $message .= "This link will expire in 1 hour.\n";

        $headers = "From: no-reply@" . parse_url(SITE_URL, PHP_URL_HOST) . "\r\n" .
                   "Reply-To: no-reply@" . parse_url(SITE_URL, PHP_URL_HOST) . "\r\n" .
                   "X-Mailer: PHP/" . phpversion();

        return $this->sendEmail($userEmail, $subject, $message, $headers);
    }
}
