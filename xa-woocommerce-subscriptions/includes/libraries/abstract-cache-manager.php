<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class HF_Cache_Manager {

	final public static function get_instance() {
		$manager = apply_filters( 'hf_cache_manager_class', 'HF_Cached_Data_Manager' );
		return new $manager;
	}
	abstract function __construct();
	abstract public function load_logger();
	abstract public function log( $message );
	abstract public function cache_and_get( $key, $callback, $params = array(), $expires = WEEK_IN_SECONDS );
	abstract public function delete_cached( $key );
}