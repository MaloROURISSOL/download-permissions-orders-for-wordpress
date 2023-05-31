<?php
/*
Plugin Name: Download Permissions for Woocommerce Orders Plugin
Description: A plugin to regenerate download permissions for completed orders with downloadable items.
Version: 1.1
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

    $count = 0;  // Initialize counter

    if( ! $orders ) {
        echo 'end';
        echo '
        <div class="wrap">
            <br><br>
            <p>Régénération des permissions de téléchargements terminée.</p>
        </div>
        wp_die();
    ';
    }

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
            <h1>Régénération des permissions de commandes Woocommerce terminées</h1>
            <p>Cette fonctionnalité peut prendre un certain temps suivant votre nombre de commandes.</p>
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
        var ccom_regenerate_offset = 0;     // Define the offset variable for tracking progress
        jQuery( document ).ready( function( $ ) {
            $( "#ccom_regenerate_button" ).click( function() {
                ccom_regenerate( $ );       // Call the ccom_regenerate function when the button is clicked
            } );
        } );

        function ccom_regenerate( $ ) {
            // Define the data to be sent in the AJAX request
            var data = {
                "action": "ccom_regenerate",
                "offset": ccom_regenerate_offset
            };
            $.post( ajaxurl, data, function( response ) {
 
                // Handle EOF Or Error
                console.log(response);
                if( response == "end" ) { return; }
 
                // Update progress text on the page
                $( "#progress" ).html( "Offset: " + ccom_regenerate_offset + " - " + (ccom_regenerate_offset + 100) + " " + response );
 
                
                ccom_regenerate_offset += 100;  // Increase the offset by 100 for the next batch of orders
                ccom_regenerate( $ );           // Recursively call the ccom_regenerate function to continue processing the next batch of orders
            } );
        }
    ', 'after' );
});