<?php

















/*add_filter( 'wp_nav_menu_items', 'add_loginout_link', 10, 2 );

function add_loginout_link( $items, $args ) {

    if (is_user_logged_in() && $args->theme_location == 'primary') {

        $items .= '<li><a href="'. wp_logout_url( get_permalink( woocommerce_get_page_id( 'myaccount' ) ) ) .'">Log Out</a></li>';

    }

    elseif (!is_user_logged_in() && $args->theme_location == 'primary') {

        $items .= '<li><a href="' . get_permalink( woocommerce_get_page_id( 'myaccount' ) ) . '">Log In</a></li>';

    }

    return $items;

}*/








//RIya filter sort catagory
//add_filter( 'woocommerce_get_catalog_ordering_args' );

// function has_woocommerce_subscription($the_user_id, $the_product_id, $the_status) {
//     $current_user = wp_get_current_user();
//     if (empty($the_user_id)) {
//         $the_user_id = $current_user->ID;
//     }
//     if (WC_Subscriptions_Manager::user_has_subscription( $the_user_id, $the_product_id, $the_status)) {
//         return true;
//     }
// }
add_filter( 'woocommerce_product_subcategories_args', 'custom_woocommerce_get_subcategories_ordering_args' );

    function custom_woocommerce_get_subcategories_ordering_args( $args ) {
      $args['orderby'] = 'title';
      return $args;
    }



?>
