<?php
/**
 * Customer on-hold order email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-on-hold-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates/Emails
 * @version     2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>
<p>
Dear
<?php echo $order->billing_first_name;?>,<br>
<?php _e( "Thank you for the order you have placed on John Snow Labs Data Markeplace. Your can find a summary of your order below:", 'woocommerce' ); ?>
</p>
<!-- <p><?php _e( "Your order is on-hold until we confirm payment has been received. Your order details are shown below for your reference:", 'woocommerce' ); ?></p>
 -->

<!-- <?php print_r($order); ?> -->
<!-- <table>
	<tr>
		<th>Product</th>
		<th>Total</th>

	</tr>
	<tr>
	<td>

	</td>
	</tr>
</table> -->

<?php

/**
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */

 do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/**
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/**
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
?>
<p>
If you have any question about your order please e-mail us at contact@johnsnowlabs.com.
</p>
<p>
Your subscriptions will be activated once the payment is processed.
</p>
<p>Regards,</p> 
<p>John Snow Labs Team</p>

<?php
do_action( 'woocommerce_email_footer', $email );
