<?php

use WBCR\Factory_Adverts_160\Base;

/**
 * Factory Adverts
 *
 * @author        Alexander Vitkalov <nechin.va@gmail.com>
 * @author        Alexander Kovalev <alex.kovalevv@gmail.com>, Github: https://github.com/alexkovalevv
 * @since         1.0.0
 *
 * @package       factory-ad-inserter
 * @copyright (c) 2019, Webcraftic Ltd
 *
 * @version       1.3.9
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'FACTORY_ADVERTS_160_LOADED' ) || ( defined( 'FACTORY_ADVERTS_BLOCK' ) && FACTORY_ADVERTS_BLOCK ) ) {
	return;
}

# Устанавливаем константу, что модуль уже загружен
define( 'FACTORY_ADVERTS_160_LOADED', true );

# Устанавливаем версию модуля
define( 'FACTORY_ADVERTS_160_VERSION', '1.6.0' );

add_action( 'init', function () {
	# Регистрируем тектовый домен, для интернализации интерфейса модуля
	load_plugin_textdomain( 'wbcr_factory_adverts_160', false, dirname( plugin_basename( __FILE__ ) ) . '/langs' );
} );

# Устанавливаем директорию модуля
define( 'FACTORY_ADVERTS_160_DIR', dirname( __FILE__ ) );

# Устанавливаем url модуля
define( 'FACTORY_ADVERTS_160_URL', plugins_url( '', __FILE__ ) );

require_once( FACTORY_ADVERTS_160_DIR . '/includes/class-rest-request.php' );
require_once( FACTORY_ADVERTS_160_DIR . '/includes/class-base.php' );

/**
 * @param Wbcr_Factory481_Plugin $plugin
 */
add_action( 'wbcr_factory_adverts_160_plugin_created', function ( $plugin ) {
	$plugin->set_adverts_manager( "WBCR\Factory_Adverts_160\Base" );
} );
