<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// customer completed order email - order complete emails are sent to the customer when the order is marked complete.

class HF_Email_Completed_Renewal_Order extends WC_Email_Customer_Completed_Order {


	function __construct() {

		$this->id             = 'customer_completed_renewal_order';
		$this->title          = __( 'Completed Renewal Order', HF_Subscriptions::TEXT_DOMAIN );
		$this->description    = __( 'Renewal order complete emails are sent to the customer when a subscription renewal order is marked complete and usually indicates that the item for that renewal period has been shipped.', HF_Subscriptions::TEXT_DOMAIN );
		$this->customer_email = true;

		$this->heading        = __( 'Your renewal order is complete', HF_Subscriptions::TEXT_DOMAIN );
		$this->subject        = sprintf( __( 'Your %1$s renewal order from %2$s is complete',  HF_Subscriptions::TEXT_DOMAIN ), '{blogname}', '{order_date}' );

		$this->template_html  = 'emails/customer-completed-renewal-order.php';
		$this->template_plain = 'emails/plain/customer-completed-renewal-order.php';
		$this->template_base  = plugin_dir_path( HF_Subscriptions::PLUGN_BASE_PATH ) . 'templates/';

		$this->heading_downloadable = $this->get_option( 'heading_downloadable', __( 'Your subscription renewal order is complete - download your files', HF_Subscriptions::TEXT_DOMAIN ) );
		$this->subject_downloadable = $this->get_option( 'subject_downloadable', sprintf( __( 'Your %1$s subscription renewal order from %2$s is complete - download your files', HF_Subscriptions::TEXT_DOMAIN ), '{blogname}', '{order_date}' ) );

		add_action( 'woocommerce_order_status_completed_renewal_notification', array( $this, 'trigger' ) );

		WC_Email::__construct();
	}

 
	function trigger( $order_id, $order = null ) {

		if ( $order_id ) {
			$this->object    = new WC_Order( $order_id );
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
		return apply_filters( 'hf_subscription_email_subject_customer_completed_renewal_order', parent::get_subject(), $this->object );
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
}