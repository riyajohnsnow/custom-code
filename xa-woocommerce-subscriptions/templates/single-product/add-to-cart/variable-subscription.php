<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

$attribute_keys = array_keys( $attributes );
$user_id = get_current_user_id();

do_action( 'woocommerce_before_add_to_cart_form' ); ?>

<form class="variations_form cart" method="post" enctype='multipart/form-data' data-product_id="<?php echo absint( $product->get_id() ); ?>" data-product_variations="<?php echo htmlspecialchars( hf_json_encode( $available_variations ) ) ?>">
	<?php do_action( 'woocommerce_before_variations_form' ); ?>

	<?php if ( empty( $available_variations ) && false !== $available_variations ) : ?>
		<p class="stock out-of-stock"><?php esc_html_e( 'This product is currently out of stock and unavailable.', HF_Subscriptions::TEXT_DOMAIN ); ?></p>
	<?php else : ?>
		<?php if ( ! $product->is_purchasable() && 0 != $user_id && 'no' != hf_get_product_limitation( $product ) && hf_is_product_limited_for_user( $product, $user_id ) ) : ?>
			<?php $resubscribe_link = hf_get_users_resubscribe_link_for_product( $product->get_id() ); ?>
			<?php if ( ! empty( $resubscribe_link ) && 'any' == hf_get_product_limitation( $product ) && hf_user_has_subscription( $user_id, $product->get_id(), hf_get_product_limitation( $product ) ) && ! hf_user_has_subscription( $user_id, $product->get_id(), 'active' ) && ! hf_user_has_subscription( $user_id, $product->get_id(), 'on-hold' ) ) : // customer has an inactive subscription, maybe offer the renewal button ?>
				<a href="<?php echo esc_url( $resubscribe_link ); ?>" class="button product-resubscribe-link"><?php esc_html_e( 'Resubscribe', HF_Subscriptions::TEXT_DOMAIN ); ?></a>
			<?php else : ?>
				<p class="limited-subscription-notice notice"><?php esc_html_e( 'You have an active subscription to this product already.', HF_Subscriptions::TEXT_DOMAIN ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<table class="variations" cellspacing="0">
				<tbody>
				<?php foreach ( $attributes as $attribute_name => $options ) : ?>
					<tr>
						<td class="label"><label for="<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>"><?php echo esc_html( wc_attribute_label( $attribute_name ) ); ?></label></td>
						<td class="value">
							<?php
							$selected = isset( $_REQUEST[ 'attribute_' . sanitize_title( $attribute_name ) ] ) ? wc_clean( $_REQUEST[ 'attribute_' . sanitize_title( $attribute_name ) ] ) : $product->get_variation_default_attribute( $attribute_name );
							wc_dropdown_variation_attribute_options( array( 'options' => $options, 'attribute' => $attribute_name, 'product' => $product, 'selected' => $selected ) );
							echo wp_kses( end( $attribute_keys ) === $attribute_name ? apply_filters( 'woocommerce_reset_variations_link', '<a class="reset_variations" href="#">' . __( 'Clear', HF_Subscriptions::TEXT_DOMAIN ) . '</a>' ) : '', array( 'a' => array( 'class' => array(), 'href' => array() ) ) );
							?>
						</td>
					</tr>
				<?php endforeach;?>
				</tbody>
			</table>

			<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

			<div class="single_variation_wrap">
				<?php
				do_action( 'woocommerce_before_single_variation' );
				do_action( 'woocommerce_single_variation' );
				do_action( 'woocommerce_after_single_variation' );
				?>
			</div>

			<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
		<?php endif; ?>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_variations_form' ); ?>
</form>

<?php
do_action( 'woocommerce_after_add_to_cart_form' );