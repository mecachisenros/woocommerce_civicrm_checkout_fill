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

			if ( $contact_id['count'] == 1) {
				$contact_id = $contact_id['values'][0]['contact_id'];

				// Get contact details
				$contact_details = civicrm_api3('Contact', 'get', array(
				  'sequential' => 1,
				  'id' => $contact_id,
				));
				$contact_details = $contact_details['values'][0];

		  		// Get billing address
		  		$is_billing = civicrm_api3('Address', 'get', array(
		  			'sequential' => 1,
		  			'contact_id' => $contact_id,
		  			'is_billing' => 1,
				));
				$is_billing = $is_billing['values'][0];

				// Get primary address
				$is_primary = civicrm_api3('Address', 'get', array(
		  			'sequential' => 1,
		  			'contact_id' => $contact_id,
		  			'is_primary' => 1,
				));
				$is_primary = $is_primary['values'][0];

				// If user has Billing address ($is_billing) use it, else use Primary address ($isprimary);
		  		if ( $is_billing['is_billing'] == 1 )  {
		  			$street_address = $is_billing['street_address'];
	  				$supplemental_address_1 = $is_billing['supplemental_address_1'];
	  				$city = $is_billing['city'];
	  				$postal_code = $is_billing['postal_code'];
	  				$country = civicrm_api3('Country', 'get', array(
									'sequential' => 1,
									'id' => $is_billing['country_id'],
								));
	  				$country = $country['values'][0]['iso_code'];
		  		} else {
		  			$street_address = $is_primary['street_address'];
	  				$supplemental_address_1 = $is_primary['supplemental_address_1'];
	  				$city = $is_primary['city'];
	  				$postal_code = $is_primary['postal_code'];
	  				$country = civicrm_api3('Country', 'get', array(
									'sequential' => 1,
									'id' => $is_primary['country_id'],
								));
	  				$country = $country['values'][0]['iso_code'];
		  		}

				// Populate Woocommerce checkout fields
				$fields['billing']['billing_first_name']['default'] = $contact_details['first_name']; // contact First name
				$fields['billing']['billing_last_name']['default'] = $contact_details['last_name']; // contact Last name
				$fields['billing']['billing_company']['default'] = $contact_details['current_employer']; // contact Current Employer
				$fields['billing']['billing_phone']['default'] = $contact_details['phone']; // contact Primary Phone
				$fields['billing']['billing_address_1']['default'] = $street_address;
				$fields['billing']['billing_address_2']['default'] = $supplemental_address_1;
				$fields['billing']['billing_city']['default'] = $city;
				$fields['billing']['billing_postcode']['default'] = WC()->customer->set_postcode( $postal_code );
				$fields['billing']['billing_country']['default'] = $country;
			  	
			}
		} 
		return $fields;
	}
}

?>
