<?php
/**
 * The template for displaying product content within loops
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $product,$post;
$postmeta = maybe_unserialize( get_post_meta( $post->ID, 'data_set_input', true ) );

// Our associative array here. id = value
$elements = array(
    '0' => 'Curated by a human expert',
    '1' => 'Normalized',
    '2' => 'Big Data Optimized',
    '3' => 'Kept up to date',
    '4' => 'Enriched with Lat/Long information',
    '5' => 'Enriched with FIPS Codes',
    '6' => 'Enriched with NPI Codes'
);
// Ensure visibility
if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}
?>

<li <?php post_class(); ?>>	
<?php

$product_title = $product->get_title();
if( is_user_logged_in()){
$customer_orders = get_posts( array(
        'numberposts' => -1,
        'meta_key'    => '_customer_user',
        'meta_value'  => get_current_user_id(),
        'post_type'   => wc_get_order_types(),
        'post_status' => array_keys( wc_get_order_statuses() )
        ) );

 $flag = 0;
foreach ($customer_orders as $key => $value) {
    $orderId = $value->ID;
    $status = $value->post_status;
    $order = new WC_Order($orderId);
    $items = $order->get_items();
    $payment_method = get_post_meta( $orderId,'_payment_method');
    $paid_date = get_post_meta($orderId,'_date_paid');
    if($status == 'wc-completed'){
        
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
    else if ($status == 'wc-cancelled'  && $paid_date['0'] != NULL){
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
//active_subscription_list();
?>
    <div class="content-product-wrp">

        <div class="product-data">
            <div class="product-list">
               <?php if($flag == 0){?>
               <div class="cart_product">
               <!-- <input type="checkbox" class="cart_check" name="checkbox" value=""> -->
               </div>
               <?php } ?> 
                <div class="span_2 col product-image-wrapper">
                    <div class="product-img">
                        <?php do_action( 'woocommerce_before_shop_loop_item_title'); ?>
                    </div>
                </div>

              
                <div class="span_8 col product-desc-wrapper">
                    <div class="description">
                        <div class="product-title">
                            <a href="<?php the_permalink(); ?>"> <?php do_action( 'woocommerce_shop_loop_item_title' );?></a>
                        </div>
                        <div class='product_excerpt'><?php echo get_excerpt(180); ?></div>

                    <?php if(!empty($postmeta)){ ?>
                    <div class="dataset-from">
                        <div class="dataset-from-title">
                            <span> Buy this dataset from us because it is: </span>
                        </div>
                        <div class="dataset-from-list">
                             <ul> 

                                <?php foreach ( $elements as $id => $element) {
                                    // If the postmeta for checkboxes exist and
                                    // this element is part of saved meta check it.
                                    if ( is_array( $postmeta ) && in_array( $id, $postmeta ) ) {

                                        ?> <li><label class="data-set"> <?php echo $element;?> </label></li>

                                    <?php } else {
                                        $checked = null;
                                    }
                                }?>
                                
                            </ul>                                                      
                        </div>
                    </div>
                        <?php } ?>
                    </div>
                </div>


                <div class="span_2 col product-button-wrapper">
                    <div class="buttons-div">
                        <div class="product-title">
                           <!--  <i class="fa fa-lock"></i>
                            <i class="fa fa-unlock"></i> -->
                            <button type="button" class="premium-btn">Premium</button>
                            <?php
                            if($flag){
                                echo '<i class="fa fa-unlock"></i>';
                               
                            }
                             else{
                                echo '<i class="fa fa-lock"></i>';
                             }
                            ?>
                        </div>

                        <div class="dataset-from-btn" >
                        <?php
                        global $product,$woocommerce;
                        $downloads = $product->get_files();
                        $sample_csv = $downloads['0']['file'];
                         ?> 
                            <form method="post" action="/download?link=<?php echo $sample_csv;?>">
                            <div class="btn-sample-data">
                                <img src="http://202.47.116.116:8224/wp-content/uploads/icons/csv.svg">
                                <input type="submit" name="" class="input-btn-data" value="Sample Data">    
                                <input type="hidden" name="link" value="<?php echo $sample_csv;?>" />
                                <input type="hidden" namd="download_id" value="<?php echo $product->id?>">
                            </div>
                             
                            </form>
                            
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

	<?php
	// /**
	//  * woocommerce_before_shop_loop_item hook.
	//  *
	//  * @hooked woocommerce_template_loop_product_link_open - 10
	//  */
	// do_action( 'woocommerce_before_shop_loop_item' );
	// /**
	//  * woocommerce_before_shop_loop_item_title hook.
	//  *
	//  * @hooked woocommerce_show_product_loop_sale_flash - 10
	//  * @hooked woocommerce_template_loop_product_thumbnail - 10
	//  */
	// do_action( 'woocommerce_before_shop_loop_item_title' );

	// /**
	//  * woocommerce_shop_loop_item_title hook.
	//  *
	//  * @hooked woocommerce_template_loop_product_title - 10
	//  */
	// do_action( 'woocommerce_shop_loop_item_title' );
	// echo "<div class='product_excerpt'>".get_excerpt(180)."</div></br>";

	// /**
	//  * woocommerce_after_shop_loop_item_title hook.
	//  *
	//  * @hooked woocommerce_template_loop_rating - 5
	//  * @hooked woocommerce_template_loop_price - 10
	//  */
	// do_action( 'woocommerce_after_shop_loop_item_title' );

	// /**
	//  * woocommerce_after_shop_loop_item hook.
	//  *
	//  * @hooked woocommerce_template_loop_product_link_close - 5
	//  * @hooked woocommerce_template_loop_add_to_cart - 10
	//  */
	// do_action( 'woocommerce_after_shop_loop_item' );
	?>
</li>
