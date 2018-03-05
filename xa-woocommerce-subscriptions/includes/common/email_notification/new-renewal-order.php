<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// new order email -  sent to the admin when a new order is received/paid for.

class HF_Email_New_Renewal_Order extends WC_Email_New_Order {

 
	function __construct() {

		$this->id             = 'new_renewal_order';
		$this->title          = __( 'New Renewal Order', HF_Subscriptions::TEXT_DOMAIN );
		$this->description    = __( 'New renewal order emails are sent when a subscription renewal payment is processed.', HF_Subscriptions::TEXT_DOMAIN );

		$this->heading        = __( 'New subscription renewal order', HF_Subscriptions::TEXT_DOMAIN );
		$this->subject        = __( '[{blogname}] New subscription renewal order ({order_number}) - {order_date}', HF_Subscriptions::TEXT_DOMAIN );

		$this->template_html  = 'emails/admin-new-renewal-order.php';
		$this->template_plain = 'emails/plain/admin-new-renewal-order.php';
		$this->template_base  = plugin_dir_path( HF_Subscriptions::PLUGN_BASE_PATH ) . 'templates/';

		add_action( 'woocommerce_order_status_pending_to_processing_renewal_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_pending_to_completed_renewal_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_pending_to_on-hold_renewal_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_failed_to_processing_renewal_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_failed_to_completed_renewal_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_failed_to_on-hold_renewal_notification', array( $this, 'trigger' ) );

		WC_Email::__construct();

		$this->recipient = $this->get_option( 'recipient' );

		if ( ! $this->recipient ) {
			$this->recipient = get_option( 'admin_email' );
		}
	}


	function trigger( $order_id, $order = null ) {

		if ( $order_id ) {
			$this->object = new WC_Order( $order_id );

			$order_date_index = array_search( '{order_date}', $this->find );
			if ( false === $order_date_index ) {
				$this->find['order-date']    = '{order_date}';
				$this->replace['order-date'] = hf_format_datetime( hf_get_objects_property( $this->object, 'date_created' ) );
			} else {
				$this->replace[ $order_date_index ] = hf_format_datetime( hf_get_objects_property( $this->object, 'date_created' ) );
			}

			$order_number_index = array_search( '{order_number}', $this->find );
			if ( false === $order_number_index ) {
				$this->find['order-number']    = '{order_number}';
				$this->replace['order-number'] = $this->object->get_order_number();
			} else {
				$this->replace[ $order_number_index ] = $this->object->get_order_number();
			}
		}

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}


	function get_content_html() {
		ob_start();
		wc_get_template(
			$this->template_html,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => true,
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
				'sent_to_admin' => true,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}
}