<?php
/**
 * Functions used by plugins
 */
if ( ! class_exists( 'Dianxiaomi_Dependencies' ) )
	require_once 'class-dianxiaomi-dependencies.php';

/**
 * WC Detection
 */
if ( ! function_exists( 'is_woocommerce_active' ) ) {
	function is_woocommerce_active() {
		return Dianxiaomi_Dependencies::woocommerce_active_check();
	}
}
