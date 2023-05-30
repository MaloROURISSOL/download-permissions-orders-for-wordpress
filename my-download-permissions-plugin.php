<?php
/*
Plugin Name: Download Permissions for Woocommerce Orders Plugin
Description: A plugin to regenerate download permissions for completed orders with downloadable items.
Version: 1.0
Author: Malo Rourissol
*/

add_action( 'wp_ajax_ccom_regenerate', function() {
    $offset = empty( $_POST['offset'] )
        ? 0 : intval( $_POST['offset'] );
    $args = [
        'status' => [ 'wc-completed' ],
        'limit' => 100,
        'offset' => $offset,
        'type' => 'shop_order',
    ];
    $orders = wc_get_orders( $args );

    if( ! $orders ) {
        echo 'Total des autorisations régénérées : ' . $offset;  // Display total number of regenerated permissions
        wp_die();
    }

    $count = 0;  // Initialize counter
    foreach( $orders as $order ) {
        echo '.';
        if( ! $order->has_downloadable_item() ) {
            continue;
        }
        $data_store = WC_Data_Store::load( 'customer-download' );
        $data_store->delete_by_order_id( $order->get_id() );
        wc_downloadable_product_permissions( $order->get_id(), true );
        $count++;  // Increment counter for each order with regenerated permissions
    }

    echo ' Autorisations régénérées dans ce groupe de commandes : ' . $count;  // Display number of regenerated permissions in this batch

    wp_die();
} );

// Add a submenu page under the Tools menu
add_action('admin_menu', 'ccom_add_submenu_page');

function ccom_add_submenu_page() {
    add_submenu_page(
        'tools.php',
        'Regenerate Download Permissions',
        'Regenerate Downloads',
        'manage_options',
        'regenerate_downloads',
        'ccom_render_submenu_page'
    );
}
add_action('admin_menu', 'ccom_add_submenu_page');

function ccom_render_submenu_page() {
    echo '
        <div class="wrap">
            <h1>Régénérer les permissions de commandes Woocommerce terminées</h1>
            <p>Cette fonctionnalité peut prendre plusieurs heures suivant votre nombre de commandes.</p>
            <a href="#" id="ccom_regenerate_button" class="button button-primary button-large">
                Régénérer les permissions de commandes
            </a>
            <br><br>
            <div id="progress" style="font-size: 17px; color: #135e96;"></div>
        </div>
    ';
}

add_action( 'admin_enqueue_scripts', function() {
    wp_enqueue_script( 'jquery' );
    wp_add_inline_script( 'jquery', '
        var ccom_regenerate_offset = 0;
        jQuery( document ).ready( function( $ ) {
            $( "#ccom_regenerate_button" ).click( function() {
                ccom_regenerate( $ );
            } );
        } );

        function ccom_regenerate( $ ) {
            var data = {
                "action": "ccom_regenerate",
                "offset": ccom_regenerate_offset
            };
            $.post( ajaxurl, data, function( response ) {
 
                // Handle EOF Or Error
                if( response == "" ) { return; }
 
                // Update progress text
                $( "#progress" ).html( "Offset: " + ccom_regenerate_offset + " " + response );
 
                // Advance
                ccom_regenerate_offset += 100;
                ccom_regenerate( $ );
            } );
        }
    ', 'after' );
});