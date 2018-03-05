<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// rebewal due invoice -  an email sent to the customer via admin.

class HF_Email_Renewal_Due_Invoice extends WC_Email_Customer_Invoice {

	var $subject_paid = null;
	var $heading_paid = null;


	function __construct() {

		$this->id             = 'renewal_due_invoice';
		$this->title          = __( 'Renewal Due Invoice', HF_Subscriptions::TEXT_DOMAIN );
		$this->description    = __( 'Sent to a customer when the subscription is due for renewal and the renewal requires a manual payment, either because it uses manual renewals or the automatic recurring payment failed. The email contains renewal order information and payment links.', HF_Subscriptions::TEXT_DOMAIN );
		$this->customer_email = true;

		$this->template_html  = 'emails/customer-renewal-invoice.php';
		$this->template_plain = 'emails/plain/customer-renewal-invoice.php';
		$this->template_base  = plugin_dir_path( HF_Subscriptions::PLUGN_BASE_PATH ) . 'templates/';

		$this->subject        = __( 'Invoice for renewal order {order_number} from {order_date}', HF_Subscriptions::TEXT_DOMAIN );
		$this->heading        = __( 'Invoice for renewal order {order_number}', HF_Subscriptions::TEXT_DOMAIN );

		add_action( 'woocommerce_generated_manual_renewal_order_renewal_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_failed_renewal_notification', array( $this, 'trigger' ) );

		WC_Email::__construct();
	}


	function trigger( $order_id, $order = null ) {

		if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( is_a( $order, 'WC_Order' ) ) {
			$this->object    = $order;
			$this->recipient = hf_get_objects_property( $this->object, 'billing_email' );

			$order_date_index = array_search( '{order_date}', $this->find );
			if ( false === $order_date_index ) {
				$this->find['order_date']    = '{order_date}';
				$this->replace['order_date'] = hf_format_datetime( hf_get_objects_property( $this->object, 'date_created' ) );
			} else {
				$this->replace[ $order_date_index ] = hf_format_datetime( hf_get_objects_property( $this->object, 'date_created' ) );
			}

			$order_number_index = array_search( '{order_number}', $this->find );
			if ( false === $order_number_index ) {
				$this->find['order_number']    = '{order_number}';
				$this->replace['order_number'] = $this->object->get_order_number();
			} else {
				$this->replace[ $order_number_index ] = $this->object->get_order_number();
			}
		}

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

 
	function get_subject() {
		return apply_filters( 'hf_subscription_email_subject_new_renewal_order', parent::get_subject(), $this->object );
	}

	function get_heading() {
		return apply_filters( 'woocommerce_email_heading_customer_renewal_order', parent::get_heading(), $this->object );
	}

	function get_content_html() {
            
		ob_start();
		wc_get_template(
			$this->template_html,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}

	function get_content_plain() {
            
		ob_start();
		wc_get_template(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}

	function init_form_fields() {

		parent::init_form_fields();

		if ( isset( $this->form_fields['heading_paid'] ) ) {
			unset( $this->form_fields['heading_paid'] );
		}

		if ( isset( $this->form_fields['subject_paid'] ) ) {
			unset( $this->form_fields['subject_paid'] );
		}

		$this->form_fields = array_merge(
			array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', HF_Subscriptions::TEXT_DOMAIN ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', HF_Subscriptions::TEXT_DOMAIN ),
					'default' => 'yes',
				),
			),
			$this->form_fields
		);
	}
}