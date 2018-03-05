<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $subscription ) ) {
	global $wp;

	if ( ! isset( $wp->query_vars['view-subscription'] ) || 'hf_shop_subscription' != get_post_type( absint( $wp->query_vars['view-subscription'] ) ) || ! current_user_can( 'view_order', absint( $wp->query_vars['view-subscription'] ) ) ) {
		echo '<div class="woocommerce-error">' . esc_html__( 'Invalid Subscription.', HF_Subscriptions::TEXT_DOMAIN ) . ' <a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" class="wc-forward">'. esc_html__( 'My Account', HF_Subscriptions::TEXT_DOMAIN ) .'</a>' . '</div>';
		return;
	}
	$subscription = hf_get_subscription( $wp->query_vars['view-subscription'] );
	
}
wc_print_notices();

?>

<table class="shop_table subscription_details">
	<tr>
		<td><?php esc_html_e( 'Status', HF_Subscriptions::TEXT_DOMAIN ); ?></td>
		<td><?php echo esc_html( hf_get_subscription_status_name( $subscription->get_status() ) ); ?></td>
	</tr>
	<tr>
		<td><?php echo esc_html_x( 'Start Date', 'table heading',  HF_Subscriptions::TEXT_DOMAIN ); ?></td>
		<td><?php echo esc_html( $subscription->get_date_to_display( 'date_created' ) ); ?></td>
	</tr>
	<?php foreach ( array(
		'last_order_date_created' => __( 'Last Order Date',  HF_Subscriptions::TEXT_DOMAIN ),
		'next_payment'            => __( 'Next Payment Date',  HF_Subscriptions::TEXT_DOMAIN ),
		'end'                     => __( 'End Date', HF_Subscriptions::TEXT_DOMAIN ),
		) as $date_type => $date_title ) : ?>
		<?php $date = $subscription->get_date( $date_type ); ?>
		<?php if ( ! empty( $date ) ) : ?>
			<tr>
				<td><?php echo esc_html( $date_title ); ?></td>
				<td><?php echo esc_html( $subscription->get_date_to_display( $date_type ) ); ?></td>
			</tr>
		<?php endif; ?>
	<?php endforeach; ?>
	<?php 
        do_action( 'hf_subscription_before_actions', $subscription );
	
        // show available action buttons
        
        $actions = hf_get_all_user_actions_for_subscription( $subscription, get_current_user_id() );
        
        ?>
	<?php if ( ! empty( $actions ) ) : 

	?>
		<tr>
		
			<td><?php esc_html_e( 'Actions', HF_Subscriptions::TEXT_DOMAIN ); ?></td>
			<td>
				<?php foreach ( $actions as $key => $action ) :
					 if( $subscription->get_status() != 'on-hold'){
				 ?>
					<a href="<?php echo esc_url( $action['url'] ); ?>" class="<?php if($action['name'] == 'Cancel'):echo 'cancel_subscribe';endif;?> button <?php echo sanitize_html_class( $key ) ?>"><?php echo esc_html( $action['name'] ); ?></a>
					<?php } else { echo "-";}?>
						<?php if($action['name'] == 'Cancel'){?>
							<div id="myModal" class="modal">
							  <!-- Modal content -->
								<div class="modal-content">
								    <div class="close-btn modal-header">
								    	<span class="close close_modal">&times;</span>
								    </div>
								    <div class="modal-body">
									    <p>Please note that a cancellation of this subscription stops future renewal charges but does not result in a refund of your order.</p>
									    <p>Are you sure you want to cancel this subscription?</p>
								    </div>
								    <div class="modal-footer">
								        <a href="<?php echo esc_url( $action['url'] ); ?>" class="btn btn-default cancel_sub text-center <?php echo sanitize_html_class( $key ) ?>" id="modal-btn-yes">Yes</a>
								        <a class="btn btn-primary no-btn text-center" id="modal-btn-no">No</a>
								    </div>
								</div>
							</div>
						<?php }?>	
				<?php endforeach; ?>
			</td>
		</tr>

	<?php endif; ?>
	<?php do_action( 'hf_subscription_after_actions', $subscription ); ?>
</table>
<?php if ( $notes = $subscription->get_customer_order_notes() ) :
	?>
	<h2><?php esc_html_e( 'Subscription Updates', HF_Subscriptions::TEXT_DOMAIN ); ?></h2>
	<ol class="commentlist notes">
		<?php foreach ( $notes as $note ) : ?>
		<li class="comment note">
			<div class="comment_container">
				<div class="comment-text">
					<p class="meta"><?php echo esc_html( date_i18n( _x( 'l jS \o\f F Y, h:ia', 'date on subscription updates list. Will be localized', HF_Subscriptions::TEXT_DOMAIN ), hf_date_to_time( $note->comment_date ) ) ); ?></p>
					<div class="description">
						<?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?>
					</div>
	  				<div class="clear"></div>
	  			</div>
				<div class="clear"></div>
			</div>
		</li>
		<?php endforeach; ?>
	</ol>
<?php endif; ?>
<?php $allow_remove_item = hf_can_items_be_removed( $subscription );?>
<h2><?php esc_html_e( 'Subscription Totals', HF_Subscriptions::TEXT_DOMAIN ); ?></h2>
<table class="shop_table order_details">
	<thead>
		<tr>
			<?php if ( $allow_remove_item ) : ?>
			<th class="product-remove" style="width: 3em;">&nbsp;</th>
			<?php endif; ?>
			<th class="product-name"><?php echo esc_html_x( 'Product', 'table headings in notification email', HF_Subscriptions::TEXT_DOMAIN ); ?></th>
			<th class="product-total"><?php echo esc_html_x( 'Total', 'table heading', HF_Subscriptions::TEXT_DOMAIN ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		if ( sizeof( $subscription_items = $subscription->get_items() ) > 0 ) {

			foreach ( $subscription_items as $item_id => $item ) {
				$_product  = apply_filters( 'hf_subscription_order_item_product', $subscription->get_product_from_item( $item ), $item );
				if ( apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
					?>
					<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'order_item', $item, $subscription ) ); ?>">
						<?php if ( $allow_remove_item ) : ?>
							<td class="remove_item"><a href="<?php echo esc_url( HF_Add_Remove_Item::get_remove_url( $subscription->get_id(), $item_id ) );?>" class="remove" onclick="return confirm('<?php printf( esc_html__( 'Are you sure you want remove this item from your subscription?', HF_Subscriptions::TEXT_DOMAIN ) ); ?>');">&times;</a></td>
						<?php endif; ?>
						<td class="product-name">
							<?php
							if ( $_product && ! $_product->is_visible() ) {
								echo esc_html( apply_filters( 'woocommerce_order_item_name', $item['name'], $item, false ) );
							} else {
								echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', sprintf( '<a href="%s">%s</a><br>', get_permalink( $item['product_id'] ), $item['name'] ), $item, false ) );
							}

							//echo wp_kses_post( apply_filters( 'woocommerce_order_item_quantity_html', ' <strong class="product-quantity">' . sprintf( '&times; %s', $item['qty'] ) . '</strong></br>', $item ) );

							do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $subscription );

							//hf_display_item_meta( $item, $subscription );

							//-- Riya : display downloads for products inside bundles
							
							$date = $subscription->get_date_to_display( 'date_created' );
                            $expire_date = date('Y-m-d', strtotime($date.'+1 years')); 
                            $order_Id = $subscription->Id - 1;
                            $payment_method = get_post_meta( $order_Id,'_payment_method');
	                        $paid_date = get_post_meta($order_Id,'_date_paid');
                					
                            // if(date("Y-m-d") <= $expire_date){
                            	
	                            if($_product && $_product->is_type('wcpb') ){
	                            	
	                                $_product_metadata = get_metadata( 'post', $_product->id);
	                               
	                                $bundle_child_products = json_decode($_product_metadata['wcpb_bundle_products'][0]);

	                                foreach ($bundle_child_products as $child_key => $child_value){

	                                    $child_product_name = $child_value->title;

	                                    $child_metadata = get_metadata( 'post', $child_key);
	                                    $file = unserialize ($child_metadata['_downloadable_files'][0]);
	                                    $child_product = wc_get_product( $child_key );
	                                    $downloads = $child_product->get_files();
	                                    
	                                    $strings = array();
	                                    $html    = '/n';
	                                    $args    = array(
	                                        'before'    => ''.$child_product_name.'<ul class ="wc-item-downloads"><li>',
	                                        'after'     => '</li></ul>',
	                                        'separator' => '</li><li>',
	                                        'show_url'  => false,
	                                    );

	                                    if ( $args['show_url'] ) {
	                                        $strings[] = '<strong class="wc-item-download-label">' . esc_html( $f_value['name'] ) . ':</strong> ' . esc_html( $f_value['file'] );
	                                    } else {

	                                        $i = 0;
	                                        foreach ($downloads as $download){
	                                            $i++;
	                                           
	                                            $prefix = count($file) > 1 ? sprintf( __( 'Download %d', 'woocommerce' ), $i ) : __( 'Download', 'woocommerce' );
	                                            if($download['id'] == '1'){
	                                            $strings[] = '<strong class="wc-item-download-label">' ."Full Data Download". ':</strong> <a href="' . esc_url( $download['file'] ) . '" target="_blank" class="dataset-csv csv-btn"><img src="http://202.47.116.116:8224/wp-content/uploads/icons/csv.svg"></a>';
	                                            
	                                            }
	                                            if($download['id'] == '2'){
	                                            $strings[] = '<strong class="wc-item-download-label">' ."Data Dictonary Download". ':</strong> <a href="' . esc_url( $download['file'] ) . '" target="_blank" class="dataset-csv csv-btn"><img src="http://202.47.116.116:8224/wp-content/uploads/icons/csv.svg"></a>';
	                                            }
	                                            if($download['id'] == '0'){
	                                            $strings[] = '<strong class="wc-item-download-label">' ."Sample Data Download". ':</strong> <a href="' . esc_url( $download['file'] ) . '" target="_blank" class="dataset-csv csv-btn"><img src="http://202.47.116.116:8224/wp-content/uploads/icons/csv.svg"></a>';
	                                            }


	                                        }
	                                    }
	                                    
	                                    if ( $strings ) {
	                                        $html = $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
	                                    }
	                                    $html = apply_filters( 'woocommerce_display_item_downloads', $html, $item, $args );
	                                    if($subscription->get_status() == "active"  && date("Y-m-d") <= $expire_date ){
	                                    echo $html;
	                                	} else if($subscription->get_status() == "cancelled"  && $paid_date['0'] != NULL && date("Y-m-d") <= $expire_date){
	                                		echo $html;

	                                	}
	                                }
	                            } else {
	                            
	                            	if($subscription->get_status() == "active" && date("Y-m-d") <= $expire_date ){
	                            	
	                                 hf_display_item_downloads( $item, $subscription_items );
	                             	}
	                             	else if(($subscription->get_status() == "cancelled" || $subscription->get_status() =="pending-cancel" )  && $paid_date['0'] != NULL && date("Y-m-d") <= $expire_date){
	                             		
	                             	 
	                             	 hf_display_item_downloads( $item, $subscription_items );	
	                             	}
	                            }
	                        // }    
							do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $subscription );
							?>
						</td>
						<td class="product-total">
							<?php echo wp_kses_post( $subscription->get_formatted_line_subtotal( $item ) ); ?>
						</td>
					</tr>
					<?php
				}

				if ( $subscription->has_status( array( 'completed', 'processing' ) ) && ( $purchase_note = get_post_meta( $_product->id, '_purchase_note', true ) ) ) {
					?>
					<tr class="product-purchase-note">
						<td colspan="3"><?php echo wp_kses_post( wpautop( do_shortcode( $purchase_note ) ) ); ?></td>
					</tr>
					<?php
				}
			}
		}
		?>
	</tbody>
	<tfoot>
		<?php

		if ( $totals = $subscription->get_order_item_totals() ) {
			foreach ( $totals as $key => $total ) {
				?>
			<tr>
				<th scope="row" <?php echo ( $allow_remove_item ) ? 'colspan="2"' : ''; ?>><?php echo esc_html( $total['label'] ); ?></th>
				<td><?php echo wp_kses_post( $total['value'] ); ?></td>
			</tr>
				<?php
			}
		} ?>
	</tfoot>
</table>

<?php do_action( 'hf_subscription_details_after_subscription_table', $subscription ); ?>

<?php wc_get_template( 'order/order-details-customer.php', array( 'order' => $subscription ) ); ?>

<div class="clear"></div>
<script type="text/javascript">

jQuery('.cancel_subscribe').on('click',function(e){
	e.preventDefault();
	jQuery( "#myModal" ).css('display','block');
});
jQuery('.close_modal , #modal-btn-no').on('click',function(){
	jQuery( "#myModal" ).css('display','none');
})
</script>