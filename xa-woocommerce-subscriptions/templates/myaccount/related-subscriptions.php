<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<header>
<?php
//echo "<pre>"; 
//print_r($subscriptions )?>
	<h2><?php esc_html_e( 'Related Subscriptions', HF_Subscriptions::TEXT_DOMAIN ); ?></h2>
</header>
<table class="shop_table shop_table_responsive my_account_orders">
	<thead>
		<tr>
			<th class="order-number"><span class="nobr"><?php esc_html_e( 'Subscription', HF_Subscriptions::TEXT_DOMAIN ); ?></span></th>
			<th class="order-date"><span class="nobr"><?php esc_html_e( 'Status', HF_Subscriptions::TEXT_DOMAIN ); ?></span></th>
			<th class="order-status"><span class="nobr"><?php echo esc_html_x( 'Next Payment', 'table heading', HF_Subscriptions::TEXT_DOMAIN ); ?></span></th>
			<th class="order-total"><span class="nobr"><?php echo esc_html_x( 'Total', 'table heading', HF_Subscriptions::TEXT_DOMAIN ); ?></span></th>
			<th class="order-actions">&nbsp;</th>
		</tr>
	</thead>
	<tbody>
	<?php
	//echo "<pre>";
	//print_r($subscriptions);
	?>
		<?php foreach ( $subscriptions as $subscription_id => $subscription ) : ?>
			<tr class="order">
				<td class="subscription-id order-number" data-title="<?php esc_attr_e( 'ID', HF_Subscriptions::TEXT_DOMAIN ); ?>">
					<a href="<?php echo esc_url( $subscription->get_view_order_url() ); ?>">
						<?php echo sprintf( esc_html_x( '#%s', 'hash before order number', HF_Subscriptions::TEXT_DOMAIN ), esc_html( $subscription->get_order_number() ) ); ?>
					</a>
				</td>
				<td class="subscription-status order-status" style="white-space:nowrap;" data-title="<?php esc_attr_e( 'Status', HF_Subscriptions::TEXT_DOMAIN ); ?>">
					<?php echo esc_attr( hf_get_subscription_status_name( $subscription->get_status() ) ); ?>
				</td>
				<td class="subscription-next-payment order-date" data-title="<?php echo esc_attr_x( 'Next Payment', 'table heading', HF_Subscriptions::TEXT_DOMAIN ); ?>">
					<?php echo esc_attr( $subscription->get_date_to_display( 'next_payment' ) ); ?>
				</td>
				<td class="subscription-total order-total" data-title="<?php echo esc_attr_x( 'Total', 'Used in data attribute. Escaped', HF_Subscriptions::TEXT_DOMAIN ); ?>">
					<?php echo wp_kses_post( $subscription->get_formatted_order_total() ); ?>
				</td>
				<td class="subscription-actions order-actions">
					<a href="<?php echo esc_url( $subscription->get_view_order_url() ) ?>" class="button view"><?php echo esc_html_x( 'View', 'view a subscription', HF_Subscriptions::TEXT_DOMAIN ); ?></a>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php do_action( 'hf_details_after_related_subscriptions_table', $subscriptions, $order_id ); ?>