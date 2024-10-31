<?php
defined( 'ABSPATH' ) || exit;

return [
	'enabled'                  => [
		'title'    => __( 'Enable', 'resolve' ),
		'type'     => 'checkbox',
		'label'    => __( 'Enable Resolve Payment Gateway', 'resolve' ),
		'default'  => 'no',
		'desc_tip' => false,
	],
	'title'                    => [
		'title'       => __( 'Title', 'resolve' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during the checkout.', 'resolve' ),
		'default'     => __( 'Resolve', 'resolve' ),
		'desc_tip'    => true,
	],
	'explainer-disable'        => [
		'title'       => __( 'Hide explainer', 'resolve' ),
		'type'        => 'checkbox',
		'label'       => __( 'Hide explainer text', 'resolve' ),
		'description' => __( 'When enabled hides checkout explainer text: "Hassle-free terms with no hidden fees. Sign up and get approved today. Learn more".', 'resolve' ),
		'default'     => 'no',
		'desc_tip'    => true,
	],
	'description-msg'          => [
		'title'       => __( 'Description', 'resolve' ),
		'type'        => 'textarea',
		'description' => __( 'Payment method description that the customer will see on the checkout page.', 'resolve' ),
		'default'     => '',
		'desc_tip'    => true,
	],
	'confirmation-msg'         => [
		'title'       => __( 'Confirmation', 'resolve' ),
		'type'        => 'textarea',
		'description' => __( 'Confirmation message that will be added to the "thank you" page.', 'resolve' ),
		'default'     => '',
		'desc_tip'    => true,
	],
	'receipt-redirect-msg'     => [
		'title'       => __( 'Receipt', 'resolve' ),
		'type'        => 'textarea',
		'description' => __( 'Message that will be added to the "receipt" page. Shown if automatic redirect is enabled.', 'resolve' ),
		'default'     => '',
		'desc_tip'    => true,
	],
	'merchant-settings'        => [
		'title'       => __( 'Merchant settings', 'resolve' ),
		'type'        => 'title',
		'description' => '',
	],
	'webshop-merchant-id'      => [
		'title' => __( 'Merchant ID', 'resolve' ),
		'type'  => 'text',
	],
	'webshop-api-key'          => [
		'title' => __( 'API Key', 'resolve' ),
		'type'  => 'password',
	],
	'test-webshop-merchant-id' => [
		'title' => __( 'TEST Merchant ID', 'resolve' ),
		'type'  => 'text',
	],
	'test-webshop-api-key'     => [
		'title' => __( 'TEST API Key', 'resolve' ),
		'type'  => 'password',
	],
	'webshop-settings'         => [
		'title'       => __( 'Webshop settings', 'resolve' ),
		'type'        => 'title',
		'description' => '',
	],
	'payment-mode'             => [
		'title'       => __( 'Payment mode', 'resolve' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'Capture mode will automatically capture all authorized Resolve orders. All automatically captured orders will be updated with an order status of; "Processing".', 'resolve' ),
		'default'     => 'authorize',
		'options'     => [
			'authorize' => __( 'Authorize', 'resolve' ),
			'capture'   => __( 'Capture', 'resolve' ),
		],
	],
	'backorder-disable'        => [
		'title'       => __( 'Backorder disable', 'resolve' ),
		'type'        => 'checkbox',
		'label'       => __( 'Disable for backordered items', 'resolve' ),
		'description' => __( 'Resolve will not display as a payment method for orders that contain backordered items.', 'resolve' ),
		'default'     => 'no',
	],
	'order-limit-min'          => [
		'title'       => __( 'Set minimum order price', 'resolve' ),
		'type'        => 'number',
		'label'       => __( 'Set minimum order price', 'resolve' ),
		'description' => __( 'Resolve will not display as a payment method for orders below the defined minimum price.', 'resolve' ),
	],
	'order-limit-max'          => [
		'title'       => __( 'Set maximum order price', 'resolve' ),
		'type'        => 'number',
		'label'       => __( 'Set maximum order price', 'resolve' ),
		'description' => __( 'Resolve will not display as a payment method for orders that exceed the defined maximum price.', 'resolve' ),
	],
	'auto-redirect'            => [
		'title'       => __( 'Automatic redirect', 'resolve' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable automatic redirect to the Resolve checkout form.', 'resolve' ),
		'description' => __( 'This option is using JavaScript (Ajax).', 'resolve' ),
		'default'     => 'yes',
		'desc_tip'    => true,
	],
	'advanced-settings'        => [
		'title'       => __( 'Advanced settings', 'resolve' ),
		'type'        => 'title',
		'description' => '',
	],
	'in-test-mode'             => [
		'title'       => __( 'Resolve Sandbox mode', 'resolve' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable Sandbox test mode.', 'resolve' ),
		'description' => __( 'Mode used for testing purposes, disable this for live web shop.', 'resolve' ),
		'default'     => 'no',
		'desc_tip'    => true,
	],
	'use-logger'               => [
		'title'       => __( 'Debug log', 'resolve' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable logging', 'resolve' ),
		// translators: path to log file.
		'description' => sprintf( __( 'Log gateway events, stored in %s. Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'resolve' ), '<code>' . WC_Log_Handler_File::get_log_file_path( RFW_PLUGIN_ID ) . '</code>' ),
		'default'     => 'no',
	],
];
