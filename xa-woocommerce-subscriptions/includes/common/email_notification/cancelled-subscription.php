<?php
if (!defined('ABSPATH')) {
    exit;
}

// cancelled subscription email - sent to the admin when a subscription is cancelled (either by a store manager, or the customer).

class HF_Email_Cancelled_Subscription extends WC_Email {

    function __construct() {

        $this->id = 'cancelled_subscription';
        $this->title = __('Cancelled Subscription', HF_Subscriptions::TEXT_DOMAIN);
        $this->description = __('Cancelled Subscription emails are sent when a customer\'s subscription is cancelled (either by a store manager, or the customer).', HF_Subscriptions::TEXT_DOMAIN);

        $this->heading = __('Subscription Cancelled', HF_Subscriptions::TEXT_DOMAIN);
        $this->subject = sprintf(__('[%s] Subscription Cancelled', HF_Subscriptions::TEXT_DOMAIN), '{blogname}');

        $this->template_html = 'emails/cancelled-subscription.php';
        $this->template_plain = 'emails/plain/cancelled-subscription.php';
        $this->template_base = plugin_dir_path(HF_Subscriptions::PLUGN_BASE_PATH) . 'templates/';

        add_action('cancelled_subscription_notification', array($this, 'trigger'));

        parent::__construct();

        $this->recipient = $this->get_option('recipient');

        if (!$this->recipient) {
            $this->recipient = get_option('admin_email');
        }
    }

    function trigger($subscription) {
        $this->object = $subscription;

        if (!is_object($subscription)) {
            $subscription = hf_get_subscription_from_key($subscription);
        }

        if (!$this->is_enabled() || !$this->get_recipient()) {
            return;
        }

        update_post_meta($subscription->get_id(), '_cancelled_email_sent', 'true');
        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }

    function get_content_html() {
        ob_start();
        wc_get_template(
                $this->template_html, array(
            'subscription' => $this->object,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => true,
            'plain_text' => false,
            'email' => $this,
                ), '', $this->template_base
        );
        return ob_get_clean();
    }

    function get_content_plain() {
        ob_start();
        wc_get_template(
                $this->template_plain, array(
            'subscription' => $this->object,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => true,
            'plain_text' => true,
            'email' => $this,
                ), '', $this->template_base
        );
        return ob_get_clean();
    }

    function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', HF_Subscriptions::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable this email notification', HF_Subscriptions::TEXT_DOMAIN),
                'default' => 'no',
            ),
            'recipient' => array(
                'title' => __('Recipient(s)', HF_Subscriptions::TEXT_DOMAIN),
                'type' => 'text',
                'description' => sprintf(__('Enter recipients (comma separated) for this email. Defaults to <code>%s</code>.', HF_Subscriptions::TEXT_DOMAIN), esc_attr(get_option('admin_email'))),
                'placeholder' => '',
                'default' => '',
            ),
            'subject' => array(
                'title' => __('Subject', HF_Subscriptions::TEXT_DOMAIN),
                'type' => 'text',
                'description' => sprintf(__('This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', HF_Subscriptions::TEXT_DOMAIN), $this->subject),
                'placeholder' => '',
                'default' => '',
            ),
            'heading' => array(
                'title' => __('Email Heading', HF_Subscriptions::TEXT_DOMAIN),
                'type' => 'text',
                'description' => sprintf(__('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', HF_Subscriptions::TEXT_DOMAIN), $this->heading),
                'placeholder' => '',
                'default' => '',
            ),
            'email_type' => array(
                'title' => __('Email type', HF_Subscriptions::TEXT_DOMAIN),
                'type' => 'select',
                'description' => __('Choose which format of email to send.', HF_Subscriptions::TEXT_DOMAIN),
                'default' => 'html',
                'class' => 'email_type',
                'options' => array(
                    'plain' => __('Plain text', HF_Subscriptions::TEXT_DOMAIN),
                    'html' => __('HTML', HF_Subscriptions::TEXT_DOMAIN),
                    'multipart' => __('Multipart', HF_Subscriptions::TEXT_DOMAIN),
                ),
            ),
        );
    }

}