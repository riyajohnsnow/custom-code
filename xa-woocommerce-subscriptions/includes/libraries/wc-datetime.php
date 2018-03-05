<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_DateTime' ) ) {
	return;
}

// WC wrapper for PHP DateTime.

class WC_DateTime extends DateTime {

	// output an ISO 8601 date string in local timezone.
	public function __toString() {
		return $this->format( DATE_ATOM );
	}

	// return int
	 
	public function getTimestamp() {
		return method_exists( 'DateTime', 'getTimestamp' ) ? parent::getTimestamp() : $this->format( 'U' );
	}

	// get the timestamp with the WordPress timezone offset added or subtracted - return int

	public function getOffsetTimestamp() {
		return $this->getTimestamp() + $this->getOffset();
	}

	/**
	 * format a date based on the offset timestamp.
	 * @param  string $format
	 * @return string
	 */
	public function date( $format ) {
		return gmdate( $format, $this->getOffsetTimestamp() );
	}

	public function date_i18n( $format = 'Y-m-d' ) {
		return date_i18n( $format, $this->getOffsetTimestamp() );
	}
}