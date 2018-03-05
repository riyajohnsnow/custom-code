<?php
/**
 * Change the heading on the Additional Information tab section title for single products.
 */
add_filter('woocommerce_product_additional_information_heading', 'isa_additional_info_heading');
function isa_additional_info_heading()
{
    return false;
}

add_action('woocommerce_after_single_product_summary', 'hooks_open_div', 7);
function hooks_open_div()
{
    global $post, $product;
    $upload_dir = wp_upload_dir();
    ?>
    <div class="produce-part">
        <ul>
            <li>
                <i><img class="imgcon" width="112" height="91"
                        src="<?php echo $upload_dir['baseurl']; ?>/2017/12/2_TPmf7V2D1y3LKi6wnbnyy2YuzuMPJZ.png"
                        style="border-radius: 0px; border: none;"></i>
                <p>Save more than 4000 hours of manual data preparation ﻿per month</p>
            </li>
            <li>
                <i><img class="imgcon" width="112" height="91"
                        src="<?php echo $upload_dir['baseurl']; ?>/2017/12/2_Jjqt3dTn3Aq3tR0lTuyEYOg7OhTZzt.png"
                        style="border-radius: 0px; border: none;"></i>
                <p>Timely integration of the ﻿latest updates</p>
            </li>
            <li>
                <i><img class="imgcon" width="112" height="91"
                        src="<?php echo $upload_dir['baseurl']; ?>/2017/12/2_6LAk3iMWc1alDeYCqiU2XpkShGgJgc.png"
                        style="border-radius: 0px; border: none;"></i>
                <p>Normalized</p>
            </li>
            <li>
                <i><img class="imgcon" width="112" height="91"
                        src="<?php echo $upload_dir['baseurl']; ?>/2017/12/2_LSpSQQTqeJgfO6lMCU91ZBegEtIEuy.png"
                        style="border-radius: 0px; border: none;"></i>
                <p>Enriched for better insights and easier integration</p>
            </li>
            <li>
                <i><img class="imgcon" width="112" height="91"
                        src="<?php echo $upload_dir['baseurl']; ?>/2017/12/2_WMr77xCoBb9qQFSHCMB6NlfHH8ueLI.png"
                        style="border-radius: 0px; border: none;"></i>
                <p>Big Data Optimized</p>
            </li>
        </ul>
    </div>
    <?php
    //Display Bundle Tab only if bundle/group product is loaded
    /*if($product->get_type() == "grouped") {
        $products = array_filter( array_map( 'wc_get_product', $product->get_children() ), 'wc_products_array_filter_visible_grouped' );
        wc_get_template( 'single-product/add-to-cart/grouped.php', array(
            'grouped_product'    => $product,
            'grouped_products'   => $products,
            'quantites_required' => false,
        ) );
    }*/
}


// Single Product Page custom tab added
add_filter('woocommerce_product_tabs', 'woo_custom_product_tabs');
function woo_custom_product_tabs($tabs)
{
    global $product;
    unset($tabs['additional_information']);
    $tabs = Array();
    //Display Tabs on single product page only
    if($product->get_type() != "grouped") {
        //Description tab
        $tabs['description_tab'] = array(
            'title' => __('Description', 'woocommerce'),
            'priority' => 80,
            'callback' => 'woo_desc_tab_content'
        );

        //Data Fields tab
        $tabs['data_info_tab'] = array(
            'title' => __('Data Info', 'woocommerce'),
            'priority' => 90,
            'callback' => 'woo_data_info_tab_content'
        );

        //Data Fields tab
        $tabs['data_fields_tab'] = array(
            'title' => __('Data Fields', 'woocommerce'),
            'priority' => 100,
            'callback' => 'woo_data_fields_tab_content'
        );

        // Data preview  tab
        $tabs['data_preview_tab'] = array(
            'title' => __('Data Preview', 'woocommerce'),
            'priority' => 110,
            'callback' => 'woo_data_preview_tab_content'
        );
    }
    return $tabs;
}

function woo_desc_tab_content(){
    ?>
    <div class="woocommerce-tabs">
        <?php
        the_content();
        ?>
    </div>
    <?php
}

//Custom tab data
function woo_data_info_tab_content()
{
    global $product;
    ?>

    <table class="shop_attributes">
        <tbody>
         <tr>
            <th>Date Created</th>
            <td><p><?php
                    $pro = get_metadata('post', $product->id, 'Date created', false);
                    echo $pro[0];
                    //echo array_shift(woocommerce_get_product_terms($product->id, 'pa_version', 'names')); ?></p>
            </td>
        </tr>
         <tr>
            <th>Last Modified</th>
            <td><p><?php
                    $pro = get_metadata('post', $product->id, 'last modified', false);
                    echo $pro[0];
                    //echo array_shift(woocommerce_get_product_terms($product->id, 'pa_version', 'names')); ?></p>
            </td>
        </tr>
        <tr>
            <th>Version</th>
            <td><p><?php
                    $pro = get_metadata('post', $product->id, 'version', false);
                    echo $pro[0];
                    //echo array_shift(woocommerce_get_product_terms($product->id, 'pa_version', 'names')); ?></p>
            </td>
        </tr>
        <tr>
            <th>Update Frequency</th>
            <td>
                <p><?php
                    $pro = get_metadata('post', $product->id, 'Update Frequency', false);
                    echo $pro[0]; ?></p>
            </td>
        </tr>
        <tr>
            <th>Temporal Coverage</th>
            <td>
                <p><?php
                    $pro = get_metadata('post', $product->id, 'Temporal Coverage', false);
                    echo $pro[0]; ?></p>
            </td>
        </tr>
        <tr>
            <th>Spatial Coverage</th>
            <td>
                <p><?php
                    $pro = get_metadata('post', $product->id, 'Spatial Coverage', false);
                    echo $pro[0]; ?></p>
            </td>
        </tr>
        <tr>
            <th>Source</th>
            <td><p><?php
                    $pro = get_metadata('post', $product->id, 'Source', false);
                    echo $pro[0]; ?></p>
            </td>
        </tr>
        <tr>
            <th>Source License URL</th>
            <td>
                <p><?php
                    $pro = get_metadata('post', $product->id, 'Source License URL', false);
                    echo $pro[0];?></p>
            </td>
        </tr>
        <tr>
            <th>Source License Requirements</th>
            <td>
                <p><?php
                    $pro = get_metadata('post', $product->id, 'Source License Requirements', false);
                    echo $pro[0]; ?></p>
            </td>
        </tr>
        <tr>
            <th>Source Citation</th>
            <td>
                <p><?php
                    $pro = get_metadata('post', $product->id, 'Source Citation', false);
                    echo $pro[0];?></p>
            </td>
        </tr>
        <tr>
            <th>Keywords</th>
            <td><p><?php
                    $pro = get_metadata('post', $product->id, 'Keywords', false);
                    echo $pro[0];
                    ?>
                </p></td>
        </tr>
        </tbody>
    </table>

    <?php

}

function woo_data_fields_tab_content()
{   
    global $product;
    echo '<div>'; ?>
    <div class="data_fields">
        <div>
            <table>
            <?php 
             $fields = get_metadata('post', $product->id, 'Data Fields', false);
            $field = $fields[0];
             ?>
                <tr>
                 <th>Name</th>
                 <th>Description</th>
                 <th>Type</th>
                 <th>Constraints</th>
                    
                </tr>
               
                <?php
                    foreach ($field as $key => $value) {
                
                    $stdArray[$key] = (array) $value;
                    echo '<tr>';
                     echo '<td>'.$stdArray[$key]['name'].'</td>';
                    
                     echo '<td>'.$stdArray[$key]['description'].'</td>';
                    
                     echo '<td>'.$stdArray[$key]['type'].'</td>';
                    
                    echo '<td>'; 
                    $constrain = $stdArray[$key]['constraints'] ;
                    //print_r((array)$constrain);
                    foreach ($constrain as $key => $value) {
                         
                      echo "{$key} : {$value} ";
                   
                    }
                    echo'</td>';
                    echo '</tr>';
                    }

                ?>
               

               
            </table>
        </div>
    </div>
    <?php echo '</div>';
}

function woo_data_preview_tab_content()
{
    global $product;
   
   if($product->get_type() != 'wcpb'){
    $fields = get_metadata('post', $product->id, '_downloadable_files');
    $csv_file = $fields[0]['0']['file'];
    $csv_file =  str_replace(' object=', '/', $csv_file);
    $csv_file =  substr($csv_file ,18, -17);
    
    $csv_file = "https://s3.amazonaws.com/$csv_file";
   // echo do_shortcode('[csvtohtml_create source_files= '.$csv_file3 .' source_type="guess" ]'); 
    echo "<html><body><table>\n\n";
    $f = fopen($csv_file, "r");
    while (($line = fgetcsv($f)) !== false) {
        echo "<tr>";
        foreach ($line as $cell) {
                echo "<td>" . htmlspecialchars($cell) . "</td>";
        }
        echo "</tr>\n";
    }
    fclose($f);
    echo "\n</table></body></html>";
   } 
}

remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
add_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);

//remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

//Remove single product page price
//remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);


remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
add_action('woocommerce_single_product_summary', 'woocommerce_my_single_title', 5);


if (!function_exists('woocommerce_my_single_title')) {
    function woocommerce_my_single_title()
    {
        global $product;

        $downloads = $product->get_files();


        ?>
        <h2 itemprop="name" class="product_title entry-title"><span><?php the_title(); ?></span>
        <?php
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
                $payment_method = get_post_meta( $orderId,'_payment_method');
                $paid_date = get_post_meta($orderId,'_date_paid');
                $order = new WC_Order($orderId);
                
                $status = $value->post_status; 
                
                $order_date =   strtotime($order->date_created .'+1 years');
                $items = $order->get_items();
                
                $current_date = strtotime(date('Y-m-d'));
                if( $paid_date['0'] != NULL && $status == 'wc-cancelled' && $current_date <= $order_date){
                    
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

                } else
                if($status == 'wc-completed' && $current_date <= $order_date ){
                    
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
            if($flag){
                echo '<i class="fa fa-unlock"></i>';
            }
            else{
                echo  '<i class="fa fa-lock"></i>';
            }               

                             
                           
                            
        ?>
            <button class="btn-premium" type="button">Premium
                <!-- <i><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" width="100%" height="100%" xml:space="preserve" style="transform: rotate(0deg);"><path fill="rgba(0, 0, 0, 1)" d="m480 320l0-16c0-26-13-49-32-64c-13-10-30-16-48-16c-18 0-35 6-48 16c-19 15-32 38-32 64l0 16l-32 0l0 192l224 0l0-192z m-64 128l-32 0l0-64l32 0z m32-128l-96 0l0-16c0-26 22-48 48-48c26 0 48 22 48 48z m-416-144l0-54c37 23 102 38 176 38c74 0 139-15 176-38l0 70l32 0l0-112c0-16-12-30-32-42c-37-23-102-38-176-38c-74 0-139 15-176 38c-20 12-32 26-32 42l0 288c0 16 12 30 32 42c37 23 102 38 176 38c17 0 33-1 48-2l0-33c-15 2-31 3-48 3c-110 0-174-34-176-48l0-54c37 23 102 38 176 38c17 0 33-1 48-2l0-33c-15 2-31 3-48 3c-110 0-174-34-176-48l0-54c37 23 102 38 176 38c17 0 33-1 48-2l0-33c-15 2-31 3-48 3c-110 0-174-34-176-48z m176-144c110 0 174 34 176 48c-2 14-66 48-176 48c-110 0-174-34-176-48c2-14 66-48 176-48z" transform="scale(0.0390625, 0.0390625)"/></svg></i> --></button>
        </h2>
        <?php 
        global $product,$woocommerce;
        $downloads = $product->get_files();
        $sample_csv = $downloads['0']['file'];
        $full_data = $downloads['1']['file'];
        $data_dictonary= $downloads['2']['file'];
        $upload_dir = wp_upload_dir();

        if($product->get_type() != "wcpb"){
        ?>
        <div class="dataset-from-btn">
            <?php 
            if($flag == 1) {
            ?>
            <form method="post" action="/download?link=<?php echo $full_data;?>">
            <div class="btn-sample-data dataset-csv">
                <img src="http://202.47.116.116:8224/wp-content/uploads/icons/csv.svg">
                <input type="submit" name="" class="input-btn-data" value="Full Dataset">    
            </div>
            </form>
            <form method="post" action="/download?link=<?php echo $data_dictonary;?>">
            <div class="btn-sample-data dataset-csv">
                <img src="http://202.47.116.116:8224/wp-content/uploads/icons/pdf.svg">
                <input type="submit" name="" class="input-btn-data" value="Data Dictonary ">   
            </div>
            </form>
            <?php
            }
            
            ?>
            
           <form method="post" action="/download?link=<?php echo $sample_csv;?>">
            <div class="btn-sample-data dataset-csv">
                <img src="http://202.47.116.116:8224/wp-content/uploads/icons/csv.svg">
                <input type="submit" name="" class="input-btn-data" value="Sample Data">    
            </div>
            </form>
            

        </div>
        <?php
        }
    }
}

/**@ Remove in all product type*/
function baztro_remove_all_quantity_fields($return)
 {
     return true;
 }

add_filter('woocommerce_is_sold_individually', 'baztro_remove_all_quantity_fields', 10);

//Change the add to cart text on single product pages woocommerce_product_thumbnails
add_filter('woocommerce_product_single_add_to_cart_text', 'woo_custom_cart_button_text');    // 2.1 +
function woo_custom_cart_button_text()
{
    global $wp,$product,$wpdb;
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
    $status = $value->post_status; 
    $items = $order->get_items();
    $payment_method = get_post_meta( $orderId,'_payment_method');
    $paid_date = get_post_meta($orderId,'_date_paid');
     if( $paid_date['0'] != NULL && $status == 'wc-cancelled'  && $current_date <= $order_date  ){
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
      }else if($status == 'wc-completed' && $current_date <= $order_date ){
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
   
    if($product->get_type() == "wcpb")
        if(!$flag){
        $button_new = __('Subscribe To Data Package', 'woocommerce');
        }else{
           echo "<div class='remove_button'></div>"; 
        }
    else
        if(!$flag){
        $button_new = __('Subscribe To Dataset', 'woocommerce');
        } else {
            echo "<div class='remove_button'></div>"; 
        }

    return $button_new;
}
/* Display related bundle on single product page */
//add_action( 'woocommerce_product_meta_end', 'get_woocommerce_product_list' );

function get_woocommerce_product_list()
{
    global $product;
    $allProducts = new WP_Query(array('post_type' => array('product', 'product_variation'), 'posts_per_page' => -1
    ));
    if (!$product->is_type('wcpb'))   //show the bundle list if the product itself is not bundle
    {
        $bundleHtml = "<div class='bundle_list_div'>";
        $bundleHtml .= "<div class='bundle_list_title'>Related Data Packages</div>";
        $bundleHtml .= "<div class='bundle_list'><ul>";
        $hasBundle = 0;

        while ($allProducts->have_posts()) {
            $allProducts->the_post();
            $bundleId = get_the_ID();
            $bundleData = get_product($bundleId);
            if ($bundleData->is_type('wcpb'))   //Check product is bundle or not
            {

                $_product_metadata = get_metadata('post', $bundleId);
                $bundle_child_products = json_decode($_product_metadata['wcpb_bundle_products'][0]);
                $childHtml = "";

                foreach ($bundle_child_products as $bundleChildId => $bundleChildValue){
                    $childHtml .= '<div><a target="_blank" href="">  '.$bundleChildValue->title.'</a></div>';
                }
                foreach ($bundle_child_products as $bundleChildId => $bundleChildValue) {
                    if ($bundleChildId == $product->id) //Check bundle product id is same as product id
                    {
                        $hasBundle = 1;
                        $bundleHtml .= '<li>';
                        $bundleHtml .= '<form method="post" enctype="multipart/form-data" >';
                        $bundleHtml .= '<a target="_blank" href="' . get_permalink($bundleId) . '">' . get_the_title($bundleId).' "Includes"</a>';
                        $bundleHtml .= '<input type="hidden" name="add-to-cart" value="' . $bundleId . '">';
                        $bundleHtml .= '<button type="submit" class="single_add_to_cart_button subscribe_add_to_cart_button">Subscribe To DataPackage</button>';
                        $bundleHtml .= '</form>';
                        $bundleHtml .= '<dl class="wcpb-cart-item-container">
                           <dt></dt>
                            <dd>'.$childHtml.'</dd>
                        </dl>';

                        $bundleHtml .= '</li>';
                    }

                }
            }
        }
        if ($hasBundle == 0) {

            $bundleHtml .= "No Related Data Packages";
        }
        $bundleHtml .= "</ul></div></div>";



        echo $bundleHtml;
    }
}
//Riya: add product image
add_filter('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);

function woocommerce_show_product_images()
{

    global $post, $product,$wpdb;
    $terms = get_the_terms( $post->ID, 'product_cat'  );
    foreach ($terms as $term) {
            
            $pro_cat[] = $term->slug; 
    }
    
    $result = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'posts WHERE post_mime_type = "image/jpeg" AND (post_date >="2018-02-20" AND post_date<"2018-02-21") ');
        
        foreach ($result as $key => $value) {    
            
            $img_name = str_replace('-data-package', '',  strtolower($value->post_title));
            
             if (in_array($img_name, $pro_cat)){
               $image_url = $value->guid;
                update_post_meta($post->ID, 'cus_product_image', $image_url);

             }
        }
   
    $columns = apply_filters('woocommerce_product_thumbnails_columns', 4);
    $thumbnail_size = apply_filters('woocommerce_product_thumbnails_large_size', 'full');
    $post_thumbnail_id = get_post_thumbnail_id($post->ID);
//    $full_size_image   = wp_get_attachment_image_src( $post_thumbnail_id, $thumbnail_size );
    $placeholder = has_post_thumbnail() ? 'with-images' : 'without-images';
    $wrapper_classes = apply_filters('woocommerce_single_product_image_gallery_classes', array(
        'woocommerce-product-gallery',
        'woocommerce-product-gallery--' . $placeholder,
        'woocommerce-product-gallery--columns-' . absint($columns),
        'images',
    ));
    $image = get_post_meta($post->ID, 'cus_product_image');
    
    ?>
    <div class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class', $wrapper_classes))); ?>"
         data-columns="<?php echo esc_attr($columns); ?>" style="opacity: 0; transition: opacity .25s ease-in-out;">
         <figure class="woocommerce-product-gallery__wrapper">
            <?php


            if (!empty($image)) {
                $html = '<div data-thumb="' . $image[0]. '" class="woocommerce-product-gallery__image"><a href="' . $image[0] . '">';
                $html .= sprintf('<img src="%s" alt="%s" class="wp-post-image" />', $image[0], esc_html__('Awaiting product image', 'woocommerce'));
                $html .= '</a></div>';
            } else {
                $html = '<div class="woocommerce-product-gallery__image--placeholder">';
                $html .= sprintf('<img src="%s" alt="%s" class="wp-post-image" />', esc_url(wc_placeholder_img_src()), esc_html__('Awaiting product image', 'woocommerce'));
                $html .= '</div>';
            }

            echo apply_filters('woocommerce_single_product_image_thumbnail_html', $html, get_post_thumbnail_id($post->ID));

            do_action('woocommerce_product_thumbnails');
            ?>
        </figure> 

    </div>


    <?php

}


//hook into the init action and call create_book_taxonomies when it fires
add_action( 'init', 'create_topics_hierarchical_taxonomy', 0 );
 
//create a custom taxonomy name it topics for your posts
 
function create_topics_hierarchical_taxonomy() {
 
// Add new taxonomy, make it hierarchical like categories
//first do the translations part for GUI
 
  $labels = array(
    'name' => _x( 'Topics', 'taxonomy general name' ),
    'singular_name' => _x( 'Topic', 'taxonomy singular name' ),
    'search_items' =>  __( 'Search Topics' ),
    'all_items' => __( 'All Topics' ),
    'parent_item' => __( 'Parent Topic' ),
    'parent_item_colon' => __( 'Parent Topic:' ),
    'edit_item' => __( 'Edit Topic' ), 
    'update_item' => __( 'Update Topic' ),
    'add_new_item' => __( 'Add New Topic' ),
    'new_item_name' => __( 'New Topic Name' ),
    'menu_name' => __( 'Topics' ),
  );    
 
// Now register the taxonomy
 
  register_taxonomy('topics',array('post'), array(
    'hierarchical' => true,
    'labels' => $labels,
    'show_ui' => true,
    'show_admin_column' => true,
    'query_var' => true,
    'rewrite' => array( 'slug' => 'topic' ),
  ));
 
}
?>

