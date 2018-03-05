<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @author      WooThemes
 * @package     WooCommerce/Templates
 * @version     2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<?php
	 global $post;
                            
$customer_orders = get_posts( array(
    'numberposts' => -1,
    'meta_key'    => '_customer_user',
    'meta_value'  => get_current_user_id(),
    'post_type'   => wc_get_order_types(),
    'post_status' => array_keys( wc_get_order_statuses() ),
) );
$upload_dir = wp_upload_dir();
?>
<table>
  <tr>
    <th>Available Subscription</th>
    <th>Expires</th>
    <th>Download</th>
     </tr>
     <?php
      foreach ($customer_orders as $key => $value) {
        $status=$value->post_status;
        $orderId = $value->ID;
        $order = new WC_Order($orderId);
        $items = $order->get_items();
        $expire_date = $value->post_date;
        $date = date('Y-m-d', strtotime($expire_date.'+1 years'));
        
      if($status == 'wc-completed') {   
        foreach ( $items as $item ) {
        $product_name = $item['name'];
        $product_id = $item['product_id'];
        $product = wc_get_product( $product_id );
      // print_r($product);
   
      if ($product->get_type() == 'wcpb'){
	            $_product_metadata = get_metadata( 'post',$product_id );
   						$bundle_child_products = json_decode($_product_metadata['wcpb_bundle_products'][0]);

    						foreach ($bundle_child_products as $child_key => $child_value){

    							 $child_product_id= $child_value->ID;
    							 $child_product_name = $child_value->title;
                   $child_metadata = get_metadata( 'post', $child_key);
        						//$file = unserialize ($child_metadata['_downloadable_files'][0]);
        						$child_product = wc_get_product( $child_key );
        						$downloads = $child_product->get_files();

      							echo '<tr>';
      							echo '<td>';
      							echo '<a href="'.get_permalink( $child_key ).'">'.$child_product_name.'</a>';
      							echo '</td>';
      							echo '<td>';
      							echo $date;
      							echo '</td>';
      							 echo '<td>';
        						foreach ($downloads as $f_key => $f_value){
                					 $name = $f_value['name'];
              						if($name == "CSV"){
                            ?>
                   	        <form method="post" action="/download?link=<?php echo $f_value['file'];?>">
                              <div class="btn-sample-data"> 
                                <input type="submit" name="" class="input-btn-data btn-default" value="Sample Dataset">    
                                <input type="hidden" name="link" value="<?php echo $f_value['file'];?>" />
                              <div>
                            </form>
                          <?php       
                   	      } else if($name == "FULL CSV"){ ?>
                          <form method="post" action="/download?link=<?php echo $f_value['file'];?>">
                            <div class="btn-sample-data"> 
                              <input type="submit" name="" class="input-btn-data btn-default" value="Full Dataset">
                              <input type="hidden" name="link" value="<?php echo $f_value['file'];?>" />
                            <div>
                          </form>
                           <?php }else { ?>
                          <form method="post" action="/download?link=<?php echo $f_value['file'];?>">
                           <div class="btn-sample-data"> 
                            <input type="submit" name="" class="input-btn-data btn-default" value="Data Dictonary">
                            <input type="hidden" name="link" value="<?php echo $f_value['file'];?>" />
                           <div>
                          </form>
                          <?php
                   	      }
                    }
    		        }
        }
  }
echo '</td>';
echo '</tr>';
        //$downloads = $order->get_downloadable_items(); 
         $downloads = $product->get_files();

        ?>
      <?php if($product->get_type() != 'wcpb'){

        ?>
     	<tr>
     	<td>
     	
     	<a href="<?php echo get_permalink( $product_id ) ?>"><?php echo esc_html($product_name); ?></a>
     	</td>

     	<td>
     	<?php echo $date  ; ?>
     	</td>
     	<td>
     	<?php
     	foreach ($downloads as $f_key => $f_value) {
     		 $name = $f_value['name'];
     		 $url = $f_value['file'];
     	if($name == "CSV"){ ?>
       <form method="post" action="/download?link=<?php echo $f_value['file'];?>">
          <div class="btn-sample-data"> 
              <input type="submit" name="" class="input-btn-data btn-default" value="Sample Dataset">
              <input type="hidden" name="link" value="<?php echo $f_value['file'];?>" />
           <div>
      </form>
     	<?php
     	} else if($name == "FULL CSV"){ ?>
       <form method="post" action="/download?link=<?php echo $f_value['file'];?>">
          <div class="btn-sample-data"> 
              <input type="submit" name="" class="input-btn-data btn-default" value="Full Dataset">
              <input type="hidden" name="link" value="<?php echo $f_value['file'];?>" />
           <div>
      </form>
      <?php
      }
      else{ ?>
     	 <form method="post" action="/download?link=<?php echo $f_value['file'];?>">
          <div class="btn-sample-data"> 
              <input type="submit" name="" class="input-btn-data btn-default" value="Data Dictonary">
              <input type="hidden" name="link" value="<?php echo $f_value['file'];?>" />
           <div>
      </form>
      <?php
     	}
     	}
     	echo '</td>';
      echo '<tr>'; 
         }
        } 
     }
                                 
?>
 </table>

 




<?php
	/**
	 * My Account dashboard.
	 *
	 * @since 2.6.0
	 */
	do_action( 'woocommerce_account_dashboard' );

	/**
	 * Deprecated woocommerce_before_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_before_my_account' );

	/**
	 * Deprecated woocommerce_after_my_account action.
	 *
	 * @deprecated 2.6.0
	 */
	do_action( 'woocommerce_after_my_account' );

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
