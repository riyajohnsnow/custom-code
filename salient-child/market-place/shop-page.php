<?php
//Riya: Change the add to cart text on product archives(on shop page) and check product is subscribe or not
add_filter( 'woocommerce_loop_add_to_cart_link', 'add_product_link' );
function add_product_link( $link ) {

    	global $product;
    	global $wp,$product,$wpdb,$post;
		$product_title = $product->get_title();
		$customer_orders = get_posts( array(
	        'numberposts' => -1,
	        'meta_key'    => '_customer_user',
	        'meta_value'  => get_current_user_id(),
	        'post_type'   => wc_get_order_types(),
	        'post_status' => array_keys( wc_get_order_statuses() ),
        ) );
 	$flag = 0;
 	if( is_user_logged_in()){
	foreach ($customer_orders as $key => $value) {
		
    $orderId = $value->ID;
    $order = new WC_Order($orderId);
    $status =  $order->status;
    $items = $order->get_items();
    $order_date =   strtotime($order->date_created .'+1 years');
    $current_date = strtotime(date('Y-m-d'));
    $payment_method = get_post_meta( $orderId,'_payment_method');
    $paid_date = get_post_meta($orderId,'_date_paid');
   	
     if($status == 'completed' && ( $current_date <= $order_date)){
	    foreach ( $items as $item ) {
	        if(!empty($item->get_meta_data())){
	            $datapackage = str_replace('1x', '', $item->get_meta_data()[0]->value);
	            $datasets=explode(",",$datapackage);
	            $datasets = array_map('trim', $datasets);
	           if (in_array(trim($product_title), $datasets))
	            {
	                $flag = 1;
	            }
	        }
	        	$product_name = $item->get_name();
	            if(strtolower(trim($product_name)) == strtolower(trim($product->get_title()))){
	                $flag = 1;
	            }
	           
	        
		}
	 }
	 else if( $paid_date['0'] != NULL && $status == 'cancelled' && $current_date <= $order_date){
	 	foreach ( $items as $item ) {
	        if(!empty($item->get_meta_data())){
	            $datapackage = str_replace('1x', '', $item->get_meta_data()[0]->value);
	            $datasets=explode(",",$datapackage);
	            $datasets = array_map('trim', $datasets);
	           if (in_array(trim($product_title), $datasets))
	            {
	                $flag = 1;
	            }
	        }
	        	$product_name = $item->get_name();
	            if(strtolower(trim($product_name)) == strtolower(trim($product->get_title()))){
	                $flag = 1;
	            }
	           
	        }
		}


	 }
	}
	$downloads = $product->get_files();
	 $full_data = $downloads['1']['file'];	
	if($flag == 1){?>
    	 <!-- echo '<a href="' .$full_data.'" class="button"  >' . __('Download Dataset', 'woocommerce') . '</a>'; -->
    	<form method="post" action="/download?link=<?php echo $full_data;?>">
         <input type="submit" name="" class="button" value="<?php echo  __('Download Dataset', 'woocommerce')?>">   
        </form>
    <?php	
    }else{
    		echo '<a href="'.$product->add_to_cart_url().'" class="button ajax_add_to_cart" data-product_id="'.$product->id.'" >' . __('Subscribe to Dataset', 'woocommerce') . '</a>';
    	}
}

//Riya: add image format to shop page
add_filter('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
/**
 * WooCommerce Loop Product Thumbs
 **/
if (!function_exists('woocommerce_template_loop_product_thumbnail')) {
    function woocommerce_template_loop_product_thumbnail()
    {

        echo woocommerce_get_product_thumbnail();
    }
}

/**
 * WooCommerce Product Thumbnail
 **/
if (!function_exists('woocommerce_get_product_thumbnail')) {

	function woocommerce_get_product_thumbnail($size = 'shop_catalog', $placeholder_width = 0, $placeholder_height = 0)
	{
		global $post, $woocommerce;

		$image = get_post_meta($post->ID, 'cus_product_image');
		$output = "";

		if (!empty($image)) {

			$output .= '<img src="' . $image[0] . '" alt="Placeholder" width="400" height="400" />';

		} else {

			$output .= '<img src="' . woocommerce_placeholder_img_src() . '" alt="Placeholder" width="' . $placeholder_width . '" height="' . $placeholder_height . '" />';
		}


		return $output;
	}
}
//Add shortcode for displaying bundle(data packages) with count in woocomerce sidebar
function display_data_packages_function()
{
    $allProducts = new WP_Query(array('post_type' => array('product', 'product_variation'), 'posts_per_page' => -1 ));
    ob_start();
    echo '<div class="bundle_product_sidebar">';
    echo '<ul>';
    while ($allProducts->have_posts()) {
        $allProducts->the_post();
        $bundleId = get_the_ID();
        $bundleData = get_product($bundleId);
        if ($bundleData->is_type('wcpb'))   //Check product is bundle or not
        {
            $_product_metadata = get_metadata('post', $bundleId);
            $bundle_child_products = json_decode($_product_metadata['wcpb_bundle_products'][0]);
            $productCount = 0;

            foreach ($bundle_child_products as $bundleChildId => $bundleChildValue) {
                $productCount++;
            }
            ?>
            <li><a href="<?php echo get_permalink();?>"><?php echo get_the_title();?>
                       </a> <span class="count"><?php echo $productCount?> </span></li>

            <?php
        }
    }
    echo '</ul>';
    echo '</div>';
    return ob_get_clean();
}
add_shortcode("display_data_packages","display_data_packages_function");
// Riya: display user subscribed dataset to search page
function display_subscription_function()
{
	ob_start();
	echo '<div class="subscribe_product_sidebar">';
	echo '<ul>';

    //echo "111";
	global $wp,$product,$wpdb;
	$product_title = $product->get_title();
	$customer_orders = get_posts( array(
		'numberposts' => -1,
		'meta_key'    => '_customer_user',
		'meta_value'  => get_current_user_id(),
		'post_type'   => 'shop_order',
		'post_status' => array_keys( wc_get_order_statuses() ),
		) );
  
	$flag = 0;

	foreach ($customer_orders as $key => $value) {
		$orderId = $value->ID;
		$order = wc_get_order($orderId);

		$items = $order->get_items();
		foreach ( $items as $item ) {
		$product_name = $item->get_name();
			if($product_name != ''){

				echo '<li>';
				echo $product_name;
				echo '</li>';
			} else {

				echo '<li>';
				echo 'No subscribed prodcuts';
				echo '</li>';
			}

		}
	}

	echo '</ul>';
	echo '</div>';
	return ob_get_clean();
    
}
add_shortcode("display_subscription","display_subscription_function");  

//Riya: multiple add to cart product on shop page
add_action('wp_footer','script_add_cart');
function script_add_cart(){
	?>
	<script type="text/javascript">
		jQuery(document).ready(function(){
			jQuery('.cart_product').on('click',function(){
				var arr = [];
				// var p_id = jQuery(this).next().find('.ajax_add_to_cart').attr("data-product_id");
				var p_id = jQuery(this).next().find('.ajax_add_to_cart').attr("data-product_id");
				console.log(p_id);
				if (jQuery('input.cart_check').is(':checked')) {
					jQuery(".click_add_cart").removeAttr("style");
					jQuery(this).find('.cart_check').val(p_id);
                        //arr.push(p_id);
                        var selected = [];
                        jQuery('.cart_check').each(function() {
                        	selected.push(jQuery(this).val());
                        });
                        selected = jQuery.grep(selected, function(n){ return (n); });
                        console.log(selected);
                        //var url = "/wp-woo/shop/?add-to-cart="+p_id;


                    }
                    else{
                    	jQuery(this).find('.cart_check').val('');
                    }
                })
		})
		jQuery(".click_add_cart").click(function(e){ 
                e.preventDefault();  // Prevent the click from going to the link
                var selected = [];
                jQuery('.cart_check').each(function() {
                	selected.push(jQuery(this).val());
                });
                selected = jQuery.grep(selected, function(n){ return (n); });
                console.log(selected);
                var data = { 
                	'action': 'myajax',
                	'selected': selected
                }      
                jQuery.ajax({
                	url: wc_add_to_cart_params.ajax_url,
                	method: 'post',
                	data: data,
                	dataType:"json",
                }).done( function (response) {
                	console.log("success");
                	window.location = '/cart';

                });
                   window.location = '/cart';
            });


        </script>
        <?php
}


 add_action( 'woocommerce_before_shop_loop', 'addToCartmultiple', 20 );
 function addToCartmultiple() {
 	if( is_shop() ) {
 		?>
 		<a class="click_add_cart button product_type_subscription add_to_cart_button  multiple" style="display:none; background: rgb(0, 152, 218); float: right;"> subscribe multiple product </a>
 		<?php
 	}
 }

add_action('wp_ajax_myajax', 'myajax_callback');
add_action('wp_ajax_nopriv_myajax', 'myajax_callback');

function myajax_callback() {        
	ob_start();
	$select[]    = $_POST['selected'];
	print_r($foo[0]);
	$selected = implode(', ', $select[0]);
	print_r($tags);

    //$product_id        = 264;
	$product_id        = $selected;
	$quantity          = 1;
	$var = array_map('intval',explode(",", $product_id));
        //$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
    //$product_status    = get_post_status( $product_id );
	foreach ($var as $key => $value) {

		if ( WC()->cart->add_to_cart( $value )) {
			do_action( 'woocommerce_ajax_added_to_cart', $value );
            echo "success";
		}
		else{
            //echo "false";
		}
	}
	die();
}
//Riya: sticky add to cart button
add_action('wp_footer','sticky_relcate');
function sticky_relcate(){

	?>

	<script type="text/javascript">
		jQuery(document).ready(function(){

			if(jQuery('.single_add_to_cart_button[id]').length){

				var stickyOffset = jQuery('#shopping-cart').offset().top;
				var producepart = jQuery('.produce-part').offset().top;
				jQuery(window).scroll(function(){                    
					var sticky = jQuery('#shopping-cart');
					scroll = jQuery(window).scrollTop()+jQuery(window).height();

					if(scroll <= producepart){
						sticky.addClass('fixed');
						jQuery('.fixed').css('position','fixed');
					}
					else
					{
						jQuery('.fixed').css('position','');
					}

				});
			}else{
				console.log('Single Product');
			}
			/* Remove Button for subscribe database*/    
			jQuery('.remove_button').next().next().css('display','none');

			var single_cart_button = jQuery('.remove_button').parent().hasClass('single_add_to_cart_button');
			if(single_cart_button ==true ){
				jQuery('.remove_button').parent().css('display','none');
			} 
            // /* Remove Button from Product Page subscribe Database*/
        })
    </script>
    <?php
}

?>