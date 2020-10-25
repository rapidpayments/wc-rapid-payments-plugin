<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters( 'wc_rapidpayment_cc_settings',
	array(
		'enabled' => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-rapidpayment-gateway-creditcard' ),
			'label'       => __( 'Enable Rapid Payments Gateway', 'woocommerce-rapidpayment-gateway-creditcard' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no'
		),
		'title' => array(
			'title'       => __( 'Title', 'woocommerce-rapidpayment-gateway-creditcard' ),
			'type'        => 'text',
			'description' => __( 'This allows Credit/Cheque Card Payments, Instant EFT Payments as well as Alternative Payment Methods (APM’s).', 'woocommerce-rapidpayment-gateway-creditcard' ),
			'default'     => __( 'Instant EFT and Credit/Cheque Card Payments', 'woocommerce-rapidpayment-gateway-creditcard' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'woocommerce-rapidpayment-gateway-creditcard' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-rapidpayment-gateway-creditcard' ),
			'default'     => __( 'Pay using EFT or your credit card details.', 'woocommerce-rapidpayment-gateway-creditcard'),
			'desc_tip'    => true,
		),
		'username' => array(
			'title'       => __( 'API Username', 'woocommerce-rapidpayment-gateway-creditcard' ),
			'type'        => 'text',
			'description' => __( 'Get your API username from your Rapid Payment account.', 'woocommerce-rapidpayment-gateway-creditcard' ),
			'default'     => '',
			'desc_tip'    => true,
		),
        'password' => array(
            'title'       => __( 'API Password', 'woocommerce-rapidpayment-gateway-creditcard' ),
            'type'        => 'password',
            'description' => __( 'Get your API password from your Rapid Payment account.', 'woocommerce-rapidpayment-gateway-creditcard' ),
            'default'     => '',
            'desc_tip'    => true,
        ),
		'logging' => array(
			'title'       => __( 'Logging', 'woocommerce-rapidpayment-gateway-creditcard' ),
			'label'       => __( 'Log debug messages', 'woocommerce-rapidpayment-gateway-creditcard' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-rapidpayment-gateway-creditcard' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
	)
);
