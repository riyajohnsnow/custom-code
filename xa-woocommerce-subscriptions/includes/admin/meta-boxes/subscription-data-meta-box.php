<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
    
//check the shipping addr http://localhost/subscription/wp-admin/post.php?post=144&action=edit
    
class HF_Meta_Box_Subscription_Data extends WC_Meta_Box_Order_Data {

                                                                
	public static function output( $post ) {
            
		global $the_subscription;

		if ( ! is_object( $the_subscription ) || $the_subscription->get_id() !== $post->ID ) {
			$the_subscription = wc_get_order( $post->ID );
		}
		$subscription = $the_subscription;
		self::init_address_fields();
		wp_nonce_field( 'woocommerce_save_data', 'hf_meta_nonce' );
                
		?>
		<style type="text/css">
			#post-body-content, #titlediv, #major-publishing-actions, #minor-publishing-actions, #visibility, #submitdiv { display:none }
		</style>
		<div class="panel-wrap woocommerce">
			<input name="post_title" type="hidden" value="<?php echo empty( $post->post_title ) ? esc_attr( get_post_type_object( $post->post_type )->labels->singular_name ) : esc_attr( $post->post_title ); ?>" />
			<input name="post_status" type="hidden" value="<?php echo esc_attr( 'wc-' . $subscription->get_status() ); ?>" />
			<div id="order_data" class="panel">

				<h2><?php
				printf( esc_html_x( 'Subscription #%s details', 'edit subscription header', HF_Subscriptions::TEXT_DOMAIN ), esc_html( $subscription->get_order_number() ) ); ?></h2>

				<div class="order_data_column_container">
					<div class="order_data_column">

						<p class="form-field form-field-wide wc-customer-user">
							<label for="customer_user"><?php esc_html_e( 'Customer:', HF_Subscriptions::TEXT_DOMAIN ) ?> <?php
							if ( $subscription->get_user_id() ) {
								$args = array(
									'post_status' => 'all',
									'post_type'      => 'hf_shop_subscription',
									'_customer_user' => absint( $subscription->get_user_id() ),
								);
								printf( '<a href="%s">%s &rarr;</a>',
									esc_url( add_query_arg( $args, admin_url( 'edit.php' ) ) ),
									esc_html__( 'View other subscriptions', HF_Subscriptions::TEXT_DOMAIN )
								);
							}
							?></label>
							<?php
							$user_string = '';
							$user_id     = '';
							if ( $subscription->get_user_id() && ( false !== get_userdata( $subscription->get_user_id() ) ) ) {
								$user_id     = absint( $subscription->get_user_id() );
								$user        = get_user_by( 'id', $user_id );
								$user_string = esc_html( $user->display_name ) . ' (#' . absint( $user->ID ) . ' &ndash; ' . esc_html( $user->user_email ) . ')';
							}
							HF_Select2::render( array(
								'class'       => 'wc-customer-search',
								'name'        => 'customer_user',
								'id'          => 'customer_user',
								'placeholder' => esc_attr__( 'Search for a customer&hellip;', HF_Subscriptions::TEXT_DOMAIN ),
								'selected'    => $user_string,
								'value'       => $user_id,
							) );
							?>
						</p>

						<p class="form-field form-field-wide">
							<label for="order_status"><?php esc_html_e( 'Subscription status:', HF_Subscriptions::TEXT_DOMAIN ); ?></label>
							<select id="order_status" name="order_status">
								<?php
								$statuses = hf_get_subscription_statuses();
								foreach ( $statuses as $status => $status_name ) {
									if ( ! $subscription->can_be_updated_to( $status ) && ! $subscription->has_status( str_replace( 'wc-', '', $status ) ) ) {
										continue;
									}
									echo '<option value="' . esc_attr( $status ) . '" ' . selected( $status, 'wc-' . $subscription->get_status(), false ) . '>' . esc_html( $status_name ) . '</option>';
								}
								?>
							</select>
						</p>

						<?php do_action( 'woocommerce_admin_order_data_after_order_details', $subscription ); ?>

					</div>
					<div class="order_data_column">
						<h4><?php esc_html_e( 'Billing Details', HF_Subscriptions::TEXT_DOMAIN ); ?> <a class="edit_address" href="#"><a href="#" class="tips load_customer_billing" data-tip="Load billing address" style="display:none;">Load billing address</a></a></h4>
						<?php

                                                echo '<div class="address">';

						if ( $subscription->get_formatted_billing_address() ) {
							echo '<p><strong>' . esc_html__( 'Address', HF_Subscriptions::TEXT_DOMAIN ) . ':</strong>' . wp_kses( $subscription->get_formatted_billing_address(), array( 'br' => array() ) ) . '</p>';
						} else {
							echo '<p class="none_set"><strong>' . esc_html__( 'Address', HF_Subscriptions::TEXT_DOMAIN ) . ':</strong> ' . esc_html__( 'No billing address set.', HF_Subscriptions::TEXT_DOMAIN ) . '</p>';
						}

						foreach ( self::$billing_fields as $key => $field ) {

							if ( isset( $field['show'] ) && false === $field['show'] ) {
								continue;
							}

							$function_name = 'get_billing_' . $key;

							if ( is_callable( array( $subscription, $function_name ) ) ) {
								$field_value = $subscription->$function_name( 'edit' );
							} else {
								$field_value = $subscription->get_meta( '_billing_' . $key );
							}

							echo '<p><strong>' . esc_html( $field['label'] ) . ':</strong> ' . wp_kses_post( make_clickable( esc_html( $field_value ) ) ) . '</p>';
						}

						echo '<p' . ( ( '' != $subscription->get_payment_method() ) ? ' class="' . esc_attr( $subscription->get_payment_method() ) . '"' : '' ) . '><strong>' . esc_html__( 'Payment Method', HF_Subscriptions::TEXT_DOMAIN ) . ':</strong>' . wp_kses_post( nl2br( $subscription->get_payment_method_to_display() ) );

						if ( '' != $subscription->get_payment_method()  && ! $subscription->is_manual() ) {
							echo hf_help_tip( sprintf( __( 'Gateway ID: [%s]', HF_Subscriptions::TEXT_DOMAIN ), $subscription->get_payment_method() ) );
						}

						echo '</p>';
						echo '</div>';
						echo '<div class="edit_address">';

						foreach ( self::$billing_fields as $key => $field ) {
							if ( ! isset( $field['type'] ) ) {
								$field['type'] = 'text';
							}

							switch ( $field['type'] ) {
								case 'select' :
									
									woocommerce_wp_select( array( 'id' => '_billing_' . $key, 'label' => $field['label'], 'options' => $field['options'], 'value' => isset( $field['value'] ) ? $field['value'] : null ) );
									break;
								default :
									
									woocommerce_wp_text_input( array( 'id' => '_billing_' . $key, 'label' => $field['label'], 'value' => isset( $field['value'] ) ? $field['value'] : null ) );
									break;
							}
						}

						echo '</div>';

						do_action( 'woocommerce_admin_order_data_after_billing_address', $subscription );
						?>
					</div>
					<div class="order_data_column">

						<h4><?php esc_html_e( 'Shipping Details', HF_Subscriptions::TEXT_DOMAIN ); ?>
							<a class="edit_address" href="#">
								<a href="#" class="tips billing-same-as-shipping" data-tip="Copy from billing" style="display:none;">Copy from billing</a>
								<a href="#" class="tips load_customer_shipping" data-tip="Load shipping address" style="display:none;">Load shipping address</a>
							</a>
						</h4>
						<?php
						
						echo '<div class="address">';

						if ( $subscription->get_formatted_shipping_address() ) {
							echo '<p><strong>' . esc_html__( 'Address', HF_Subscriptions::TEXT_DOMAIN ) . ':</strong>' . wp_kses( $subscription->get_formatted_shipping_address(), array( 'br' => array() ) ) . '</p>';
						} else {
							echo '<p class="none_set"><strong>' . esc_html__( 'Address', HF_Subscriptions::TEXT_DOMAIN ) . ':</strong> ' . esc_html__( 'No shipping address set.', HF_Subscriptions::TEXT_DOMAIN ) . '</p>';
						}

						if ( self::$shipping_fields ) {
							foreach ( self::$shipping_fields as $key => $field ) {
								if ( isset( $field['show'] ) && false === $field['show'] ) {
									continue;
								}

								$function_name = 'get_shipping_' . $key;

								if ( is_callable( array( $subscription, $function_name ) ) ) {
									$field_value = $subscription->$function_name( 'edit' );
								} else {
									$field_value = $subscription->get_meta( '_shipping_' . $key );
								}

								echo '<p><strong>' . esc_html( $field['label'] ) . ':</strong> ' . wp_kses_post( make_clickable( esc_html( $field_value ) ) ) . '</p>';
							}
						}

						if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' == get_option( 'woocommerce_enable_order_comments', 'yes' ) ) && $post->post_excerpt ) {
							echo '<p><strong>' . esc_html__( 'Customer Note:', HF_Subscriptions::TEXT_DOMAIN ) . '</strong> ' . wp_kses_post( nl2br( $post->post_excerpt ) ) . '</p>';
						}

						echo '</div>';

						echo '<div class="edit_address">';

						if ( self::$shipping_fields ) {
							foreach ( self::$shipping_fields as $key => $field ) {
								if ( ! isset( $field['type'] ) ) {
									$field['type'] = 'text';
								}

								switch ( $field['type'] ) {
									case 'select' :
										woocommerce_wp_select( array( 'id' => '_shipping_' . $key, 'label' => $field['label'], 'options' => $field['options'] ) );
										break;
									default :
										woocommerce_wp_text_input( array( 'id' => '_shipping_' . $key, 'label' => $field['label'] ) );
										break;
								}
							}
						}

						if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' == get_option( 'woocommerce_enable_order_comments', 'yes' ) ) ) {
							?>
							<p class="form-field form-field-wide"><label for="excerpt"><?php esc_html_e( 'Customer Note:', HF_Subscriptions::TEXT_DOMAIN ) ?></label>
								<textarea rows="1" cols="40" name="excerpt" tabindex="6" id="excerpt" placeholder="<?php esc_attr_e( 'Customer\'s notes about the order', HF_Subscriptions::TEXT_DOMAIN ); ?>"><?php echo wp_kses_post( $post->post_excerpt ); ?></textarea></p>
								<?php
						}

						echo '</div>';

						do_action( 'woocommerce_admin_order_data_after_shipping_address', $subscription );
						?>
					</div>
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}

	public static function save( $post_id, $post = '' ) {
            
		global $wpdb;

		if ( 'hf_shop_subscription' != $post->post_type || empty( $_POST['hf_meta_nonce'] ) || ! wp_verify_nonce( $_POST['hf_meta_nonce'], 'woocommerce_save_data' ) ) {
			return;
		}

		self::init_address_fields();
		add_post_meta( $post_id, '_order_key', uniqid( 'order_' ), true );
		update_post_meta( $post_id, '_customer_user', absint( $_POST['customer_user'] ) );

		if ( self::$billing_fields ) {
			foreach ( self::$billing_fields as $key => $field ) {

				if ( ! isset( $_POST[ '_billing_' . $key ] ) ) {
					continue;
				}
				update_post_meta( $post_id, '_billing_' . $key, wc_clean( $_POST[ '_billing_' . $key ] ) );
			}
		}

		if ( self::$shipping_fields ) {
			foreach ( self::$shipping_fields as $key => $field ) {

				if ( ! isset( $_POST[ '_shipping_' . $key ] ) ) {
					continue;
				}
				update_post_meta( $post_id, '_shipping_' . $key, wc_clean( $_POST[ '_shipping_' . $key ] ) );
			}
		}

		$subscription = hf_get_subscription( $post_id );

		try {
			if ( 'cancelled' == $_POST['order_status'] ) {
				$subscription->cancel_order();
			} else {
				$subscription->update_status( $_POST['order_status'], '', true );
			}
		} catch ( Exception $e ) {
			hf_add_admin_notice( sprintf( __( 'Error updating some information: %s', HF_Subscriptions::TEXT_DOMAIN ), $e->getMessage() ), 'error' );
		}

		do_action( 'woocommerce_process_hf_shop_subscription_meta', $post_id, $post );
	}

}