<?php
/*
    Plugin Name: Woocommerce CiviCRM Populate Address
    Plugin URI: 
    Description: Plugin for populating Woocommerce checkout details with CiviCRM contact details, it only retrieves data from CiviCRM, it does not write to the database, it must be used in conjuction with Veda Consulting's Woocommerce Integration plugin which handles create/update user's details
    Author: Andrei Mondoc (andreimondoc at gmail.com)
    Version: 0.1
    Author URI:
    */

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    
	add_filter( 'woocommerce_checkout_fields' , 'woocommerce_civicrm_populate_address' );
	// Hook in Woocommerece's checkout
	function woocommerce_civicrm_populate_address( $fields ) {

		// Get data only if user is logged in
		if ( is_user_logged_in() ) {

			// Get current user
			$current_user = wp_get_current_user();
			$user_id = $current_user->ID;

			// Bootstrap CiviCRM
			if ( !civicrm_wp_initialize() ) {
	    		return;
	  		}

	  		// Get CiviCRM contact_id
	  		$contact_id = civicrm_api3('UFMatch', 'get', array(
	  			'sequential' => 1,
	  			'uf_id' => $user_id,
			));

			if ( $contact_id['count'] == 1 && $contact_id['values'] != '' ) {
				$contact_id = $contact_id['values'][0]['contact_id'];
				
				/*
				Not necesary, deal only with addess fields
				$contact_details = civicrm_api3('Contact', 'get', array(
				  'sequential' => 1,
				  'id' => $contact_id,
				));
				$contact_details = $contact_details['values'][0];
				*/

		  		// Get billing address
		  		$is_billing = civicrm_api3('Address', 'get', array(
		  			'sequential' => 1,
		  			'contact_id' => $contact_id,
		  			'location_type_id' => 'Billing',
				));
				$is_billing = $is_billing['values'][0];

				// If user has Billing address ($is_billing)
		  		if ( $is_billing['is_billing'] == 1 )  {
		  			$street_address = $is_billing['street_address'];
	  				$supplemental_address_1 = $is_billing['supplemental_address_1'];
	  				$city = $is_billing['city'];
	  				$postal_code = $is_billing['postal_code'];
	  				$name = $is_billing['name'];
	  				$name = explode(' ', $name);
	  				$country = civicrm_api3('Country', 'get', array(
									'sequential' => 1,
									'id' => $is_billing['country_id'],
								));
	  				$country = $country['values'][0]['iso_code'];
		  		} else {
		  			return $fields;
		  		}

				// Update woocommerce meta data before the form is loaded
				update_user_meta( $user_id, 'billing_first_name', $name[0] );
				update_user_meta( $user_id, 'billing_last_name', $name[1] );
				update_user_meta( $user_id, 'billing_address_1', $street_address );
				update_user_meta( $user_id, 'billing_address_2', $supplemental_address_1 );
				update_user_meta( $user_id, 'billing_city', $city );
				update_user_meta( $user_id, 'billing_postcode', $postal_code );
				update_user_meta( $user_id, 'billing_country', $country );
			  	
			}
		} 
		return $fields;
	}
	
	// Update/Create billing address form Woocommerce's My-Account page
	add_action( 'woocommerce_customer_save_address', 'woocommerce_civicrm_update_civi_address', 20 );

	function woocommerce_civicrm_update_civi_address( $user_id ) {

		$current_user = wp_get_current_user();
		$user_id = $current_user->ID;

		// Get all user meta data
		$user_meta = array_map( function( $a ){ return $a[0]; }, get_user_meta( $user_id ) );

		//$billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);

		$contact_id = civicrm_api3('UFMatch', 'get', array(
	  		'sequential' => 1,
	  		'uf_id' => $user_id,
		));
		$contact_id = $contact_id['values'][0]['contact_id'];

		$existing_billing = civicrm_api3('Address', 'get', array(
		  	'sequential' => 1,
		  	'contact_id' => $contact_id,
		  	'location_type_id' => 'Billing',
		));
		//$existing_billing = $existing_billing['values'][0];

		if ( $existing_billing['count'] != 0 && $existing_billing['values'] != '' ) {

			$country_id = civicrm_api3('Country', 'get', array(
				'sequential' => 1,
				'iso_code' => $user_meta['billing_country'],
			));

			// Update existing billing address
			$is_billing = civicrm_api3('Address', 'create', array(
			  	'sequential' => 1,
			  	'contact_id' => $contact_id,
			  	'id' => $existing_billing['id'],
			  	'street_address' => $user_meta['billing_address_1'],
			  	'supplemental_address_1' => $user_meta['billing_address_2'],
			  	'city' => $user_meta['billing_city'],
			  	'postal_code' => $user_meta['billing_postcode'],
			  	'name' => $user_meta['billing_first_name'].' '.$user_meta['billing_last_name'],
			  	'country_id' => $country_id['id'],
			));
		} else {
			// create new Address
			$is_billing = civicrm_api3('Address', 'create', array(
			  	'sequential' => 1,
			  	'contact_id' => $contact_id,
			  	'location_type_id' => 'Billing',
			  	'street_address' => $user_meta['billing_address_1'],
			  	'supplemental_address_1' => $user_meta['billing_address_2'],
			  	'city' => $user_meta['billing_city'],
			  	'postal_code' => $user_meta['billing_postcode'],
			  	'name' => $user_meta['billing_first_name'].' '.$user_meta['billing_last_name'],
			  	'country_id' => $country_id['id'],
			));
		}
	}
}

?>
