<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// display a human raedable time diff for a given timestamp, eg:- "In 8 hours" , "8 hours ago"...

function hf_get_readable_time_diff( $timestamp_gmt ) {

	$time_diff = $timestamp_gmt - current_time( 'timestamp', true );

	if ( $time_diff > 0 && $time_diff < WEEK_IN_SECONDS ) {
		$date_to_display = sprintf( __( 'In %s', HF_Subscriptions::TEXT_DOMAIN ), human_time_diff( current_time( 'timestamp', true ), $timestamp_gmt ) );
	} elseif ( $time_diff < 0 && absint( $time_diff ) < WEEK_IN_SECONDS ) {
		$date_to_display = sprintf( __( '%s ago', HF_Subscriptions::TEXT_DOMAIN ), human_time_diff( current_time( 'timestamp', true ), $timestamp_gmt ) );
	} else {
		$timestamp_site  = hf_date_to_time( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp_gmt ) ) );
		$date_to_display = date_i18n( wc_date_format(), $timestamp_site ) . ' ' . date_i18n( wc_time_format(), $timestamp_site );
	}

	return $date_to_display;
}

function hf_get_subscription_period_strings( $number = 1, $period = '' ) {

	$translated_periods = apply_filters( 'hf_subscription_periods',
		array(
			'day'   => sprintf( _nx( 'day',   '%s days',   $number, 'Subscription billing period.', HF_Subscriptions::TEXT_DOMAIN ), $number ),
			'week'  => sprintf( _nx( 'week',  '%s weeks',  $number, 'Subscription billing period.', HF_Subscriptions::TEXT_DOMAIN ), $number ),
			'month' => sprintf( _nx( 'month', '%s months', $number, 'Subscription billing period.', HF_Subscriptions::TEXT_DOMAIN ), $number ),
			'year'  => sprintf( _nx( 'year',  '%s years',  $number, 'Subscription billing period.', HF_Subscriptions::TEXT_DOMAIN ), $number ),
		)
	);

	return ( ! empty( $period ) ) ? $translated_periods[ $period ] : $translated_periods;
}

function hf_get_subscription_trial_period_strings( $number = 1, $period = '' ) {

	$translated_periods = apply_filters( 'hf_subscription_trial_periods',
		array(
			'day'   => sprintf( _n( '%s day', 'a %s-day', $number, HF_Subscriptions::TEXT_DOMAIN ), $number ),
			'week'  => sprintf( _n( '%s week', 'a %s-week', $number, HF_Subscriptions::TEXT_DOMAIN ), $number ),
			'month' => sprintf( _n( '%s month', 'a %s-month', $number, HF_Subscriptions::TEXT_DOMAIN ), $number ),
			'year'  => sprintf( _n( '%s year', 'a %s-year', $number, HF_Subscriptions::TEXT_DOMAIN ), $number ),
		)
	);

	return ( ! empty( $period ) ) ? $translated_periods[ $period ] : $translated_periods;
}

function hf_get_non_cached_subscription_ranges() {

	foreach ( array( 'day', 'week', 'month', 'year' ) as $period ) {

		$subscription_lengths = array(
			__( 'Never expire', HF_Subscriptions::TEXT_DOMAIN ),
		);

		switch ( $period ) {
			case 'day':
				$subscription_lengths[] = __( '1 day', HF_Subscriptions::TEXT_DOMAIN );
				$subscription_range = range( 2, 90 );
				break;
			case 'week':
				$subscription_lengths[] = __( '1 week', HF_Subscriptions::TEXT_DOMAIN );
				$subscription_range = range( 2, 52 );
				break;
			case 'month':
				$subscription_lengths[] = __( '1 month', HF_Subscriptions::TEXT_DOMAIN );
				$subscription_range = range( 2, 24 );
				break;
			case 'year':
				$subscription_lengths[] = __( '1 year', HF_Subscriptions::TEXT_DOMAIN );
				$subscription_range = range( 2, 5 );
				break;
		}

		foreach ( $subscription_range as $number ) {
			$subscription_range[ $number ] = hf_get_subscription_period_strings( $number, $period );
		}

		$subscription_lengths += $subscription_range;

		$subscription_ranges[ $period ] = $subscription_lengths;
	}

	return $subscription_ranges;
}

function hf_get_subscription_ranges( $subscription_period = '' ) {
	if ( ! is_string( $subscription_period ) ) {
		$subscription_period = '';
	}

	$locale = get_locale();

	$subscription_ranges = HF_Subscriptions::$cache->cache_and_get( 'hf-sub-ranges-' . $locale, 'hf_get_non_cached_subscription_ranges', array(), 3 * HOUR_IN_SECONDS );

	$subscription_ranges = apply_filters( 'hf_subscription_lengths', $subscription_ranges, $subscription_period );

	if ( ! empty( $subscription_period ) ) {
		return $subscription_ranges[ $subscription_period ];
	} else {
		return $subscription_ranges;
	}
}

function hf_get_subscription_period_interval_strings( $interval = '' ) {

	$intervals = array( 1 => __( 'every', HF_Subscriptions::TEXT_DOMAIN ) );

	foreach ( range( 2, 6 ) as $i ) {
		$intervals[ $i ] = sprintf( __( 'every %s', HF_Subscriptions::TEXT_DOMAIN ), HF_Subscriptions::append_numeral_suffix( $i ) );
	}

	$intervals = apply_filters( 'hf_subscription_period_interval_strings', $intervals );

	if ( empty( $interval ) ) {
		return $intervals;
	} else {
		return $intervals[ $interval ];
	}
}

function hf_get_available_time_periods( $form = 'singular' ) {

	$number = ( 'singular' === $form ) ? 1 : 2;

	$translated_periods = apply_filters( 'hf_subscription_available_time_periods',
		array(
			'day'   => _nx( 'day',   'days',   $number, 'Used in the trial period dropdown. Number is in text field. 0, 2+ will need plural, 1 will need singular.', HF_Subscriptions::TEXT_DOMAIN ),
			'week'  => _nx( 'week',  'weeks',  $number, 'Used in the trial period dropdown. Number is in text field. 0, 2+ will need plural, 1 will need singular.', HF_Subscriptions::TEXT_DOMAIN ),
			'month' => _nx( 'month', 'months', $number, 'Used in the trial period dropdown. Number is in text field. 0, 2+ will need plural, 1 will need singular.', HF_Subscriptions::TEXT_DOMAIN ),
			'year'  => _nx( 'year',  'years',  $number, 'Used in the trial period dropdown. Number is in text field. 0, 2+ will need plural, 1 will need singular.', HF_Subscriptions::TEXT_DOMAIN ),
		)
	);

	return $translated_periods;
}

function hf_get_subscription_trial_lengths( $subscription_period = '' ) {

	$all_trial_periods = hf_get_subscription_ranges();

	foreach ( $all_trial_periods as $period => $trial_periods ) {
		$all_trial_periods[ $period ][0] = __( 'no', HF_Subscriptions::TEXT_DOMAIN );
	}

	if ( ! empty( $subscription_period ) ) {
		return $all_trial_periods[ $subscription_period ];
	} else {
		return $all_trial_periods;
	}
}

function hf_add_time( $number_of_periods, $period, $from_timestamp ) {

	if ( $number_of_periods > 0 ) {
		if ( 'month' == $period ) {
			$next_timestamp = hf_add_months( $from_timestamp, $number_of_periods );
		} else {
			$next_timestamp = hf_strtotime_exceptional( "+ {$number_of_periods} {$period}", $from_timestamp );
		}
	} else {
		$next_timestamp = $from_timestamp;
	}

	return $next_timestamp;
}

function hf_add_months( $from_timestamp, $months_to_add ) {

	$first_day_of_month = gmdate( 'Y-m', $from_timestamp ) . '-1';
	$days_in_next_month = gmdate( 't', hf_strtotime_exceptional( "+ {$months_to_add} month", hf_date_to_time( $first_day_of_month ) ) );

	if ( gmdate( 'd m Y', $from_timestamp ) === gmdate( 't m Y', $from_timestamp ) || gmdate( 'd', $from_timestamp ) > $days_in_next_month ) {
		for ( $i = 1; $i <= $months_to_add; $i++ ) {
			$next_month = hf_add_time( 3, 'days', $from_timestamp ); 
			$next_timestamp = $from_timestamp = hf_date_to_time( gmdate( 'Y-m-t H:i:s', $next_month ) );
		}
	} else { 
		$next_timestamp = hf_strtotime_exceptional( "+ {$months_to_add} month", $from_timestamp );
	}

	return $next_timestamp;
}

function hf_estimate_periods_between( $start_timestamp, $end_timestamp, $unit_of_time = 'month', $rounding_method = 'ceil' ) {

	if ( $end_timestamp <= $start_timestamp ) {

		$periods_until = 0;

	} elseif ( 'month' == $unit_of_time ) {

		$timestamp = $start_timestamp;

		if ( 'ceil' == $rounding_method ) {
			for ( $periods_until = 0; $timestamp < $end_timestamp; $periods_until++ ) {
				$timestamp = hf_add_months( $timestamp, 1 );
			}
		} else {
			for ( $periods_until = -1; $timestamp <= $end_timestamp; $periods_until++ ) {
				$timestamp = hf_add_months( $timestamp, 1 );
			}
		}
	} else {

		$seconds_until_timestamp = $end_timestamp - $start_timestamp;

		switch ( $unit_of_time ) {

			case 'day' :
				$denominator = DAY_IN_SECONDS;
				break;

			case 'week' :
				$denominator = WEEK_IN_SECONDS;
				break;

			case 'year' :
				$denominator = YEAR_IN_SECONDS;
				$seconds_until_timestamp = $seconds_until_timestamp - hf_number_of_leap_days( $start_timestamp, $end_timestamp ) * DAY_IN_SECONDS;
				break;
		}

		$periods_until = ( 'ceil' == $rounding_method ) ? ceil( $seconds_until_timestamp / $denominator ) : floor( $seconds_until_timestamp / $denominator );
	}

	return $periods_until;
}

function hf_number_of_leap_days( $start_timestamp, $end_timestamp ) {
	if ( ! is_numeric( $start_timestamp ) || ! is_numeric( $end_timestamp ) ) {
		throw new InvalidArgumentException( 'Start or end times are not integers' );
	}
	$default_tz = date_default_timezone_get();
	date_default_timezone_set( 'UTC' );

	$years = range( date( 'Y', $start_timestamp ), date( 'Y', $end_timestamp ) );
	$leap_years = array_filter( $years, 'hf_is_leap_year' );
	$total_feb_29s = 0;

	if ( ! empty( $leap_years ) ) {
		$first_feb_29 = mktime( 23, 59, 59, 2, 29, reset( $leap_years ) );
		$last_feb_29 = mktime( 0, 0, 0, 2, 29, end( $leap_years ) );
		$is_first_feb_covered = ( $first_feb_29 >= $start_timestamp ) ? 1: 0;
		$is_last_feb_covered = ( $last_feb_29 <= $end_timestamp ) ? 1: 0;

		if ( count( $leap_years ) > 1 ) {
			$total_feb_29s = count( $leap_years ) - 2 + $is_first_feb_covered + $is_last_feb_covered;
		} else {
			$total_feb_29s = ( $first_feb_29 >= $start_timestamp && $last_feb_29 <= $end_timestamp ) ? 1: 0;
		}
	}
	date_default_timezone_set( $default_tz );

	return $total_feb_29s;
}

function hf_is_leap_year( $year ) {
	return date( 'L', mktime( 0, 0, 0, 1, 1, $year ) );
}

function hf_estimate_period_between( $last_date, $second_date, $interval = 1 ) {

	if ( ! is_int( $interval ) ) {
		$interval = 1;
	}

	$last_timestamp    = hf_date_to_time( $last_date );
	$second_timestamp  = hf_date_to_time( $second_date );

	$earlier_timestamp = min( $last_timestamp, $second_timestamp );
	$later_timestamp   = max( $last_timestamp, $second_timestamp );

	$days_in_month     = gmdate( 't', $earlier_timestamp );
	$difference        = absint( $last_timestamp - $second_timestamp );
	$period_in_seconds = round( $difference / $interval );
	$possible_periods  = array();

	$full_months = hf_find_full_months_between( $earlier_timestamp, $later_timestamp, $interval );

	$possible_periods['month'] = array(
		'intervals'         => floor( $full_months['months'] / $interval ),
		'remainder'         => $full_months['remainder'],
		'fraction'          => $full_months['remainder'] / ( 30 * DAY_IN_SECONDS ),
		'period'            => 'month',
		'days_in_month'     => $days_in_month,
		'original_interval' => $interval,
	);

	foreach ( array( 'year' => YEAR_IN_SECONDS, 'week' => WEEK_IN_SECONDS, 'day' => DAY_IN_SECONDS ) as $time => $seconds ) {
		$possible_periods[ $time ] = array(
			'intervals'         => floor( $period_in_seconds / $seconds ),
			'remainder'         => $period_in_seconds % $seconds,
			'fraction'          => ($period_in_seconds % $seconds) / $seconds,
			'period'            => $time,
			'days_in_month'     => $days_in_month,
			'original_interval' => $interval,
		);
	}

	$possible_periods_zero_filtered = array_filter( $possible_periods, 'hf_discard_zero_intervals' );
	if ( empty( $possible_periods_zero_filtered ) ) {
		return 'day';
	} else {
		$possible_periods = $possible_periods_zero_filtered;
	}

	$possible_periods_no_hd = array_filter( $possible_periods, 'hf_discard_high_deviations' );

	if ( count( $possible_periods_no_hd ) == 1 ) {
		$possible_periods_no_hd = array_shift( $possible_periods_no_hd );
		return $possible_periods_no_hd['period'];
	} elseif ( count( $possible_periods_no_hd ) > 1 ) {
		$possible_periods = $possible_periods_no_hd;
	}

	$possible_periods_interval_match = array_filter( $possible_periods, 'hf_match_intervals' );

	if ( count( $possible_periods_interval_match ) == 1 ) {
		foreach ( $possible_periods_interval_match as $period_data ) {
			return $period_data['period'];
		}
	} elseif ( count( $possible_periods_interval_match ) > 1 ) {
		$possible_periods = $possible_periods_interval_match;
	}

	usort( $possible_periods, 'hf_sort_by_intervals' );
	$least_interval = array_shift( $possible_periods );

	return $least_interval['period'];
}

function hf_find_full_months_between( $start_timestamp, $end_timestamp, $interval = 1 ) {
	$number_of_months = 0;
	$remainder = 0;
	$previous_remainder = 0;
	$months_in_period = 0;
	$remainder_in_period = 0;

	while ( 0 <= $remainder ) {
		$previous_timestamp = $start_timestamp;
		$start_timestamp = hf_add_months( $start_timestamp, 1 );
		$previous_remainder = $remainder;
		$remainder = $end_timestamp - $start_timestamp;
		$remainder_in_period += $start_timestamp - $previous_timestamp;

		if ( $remainder >= 0 ) {
			$number_of_months++;
			$months_in_period++;
		} elseif ( 0 === $previous_remainder ) {
			$previous_remainder = $end_timestamp - $previous_timestamp;
		}

		if ( $months_in_period >= $interval ) {
			$months_in_period = 0;
			$remainder_in_period = 0;
		}
	}

	$remainder_in_period += $remainder;

	$time_difference = array(
		'months' => $number_of_months,
		'remainder' => $remainder_in_period,
	);

	return $time_difference;
}

function hf_discard_zero_intervals( $array ) {
	return $array['intervals'] > 0;
}

function hf_discard_high_deviations( $array ) {
	switch ( $array['period'] ) {
		case 'year':
			return $array['fraction'] < ( 10 / 365 );
			break;
		case 'month':
			return $array['fraction'] < ( 4 / $array['days_in_month'] );
			break;
		case 'week':
			return $array['fraction'] < ( 1 / 7 );
			break;
		case 'day':
			return $array['fraction'] < ( 1 / 24 );
			break;
		default:
			return false;
	}
}

function hf_match_intervals( $array ) {
	return $array['intervals'] == $array['original_interval'];
}

function hf_sort_by_intervals( $a, $b ) {
	if ( $a['intervals'] == $b['intervals'] ) {
		if ( $a['fraction'] == $b['fraction'] ) {
			return 0;
		}
		return ( $a['fraction'] < $b['fraction'] ) ? -1 : 1;

	}
	return ( $a['intervals'] < $b['intervals'] ) ? -1 : 1;
}

function hf_sort_by_fractions( $a, $b ) {
	if ( $a['fraction'] == $b['fraction'] ) {
		return 0;
	}
	return ( $a['fraction'] > $b['fraction'] ) ? -1 : 1;
}

function hf_is_datetime_mysql_format( $time ) {
	if ( ! is_string( $time ) ) {
		return false;
	}

	if ( function_exists( 'strptime' ) ) {
		$valid_time = $match = ( false !== strptime( $time, '%Y-%m-%d %H:%M:%S' ) ) ? true : false;
	} else {
		$match = preg_match( '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $time );
		$valid_time = hf_date_to_time( $time );
	}

	return ( $match && false !== $valid_time && -2209078800 <= $valid_time ) ? true : false;
}

function hf_date_to_time( $date_string ) {

	if ( 0 == $date_string ) {
		return 0;
	}
	$date_obj = new DateTime( $date_string, new DateTimeZone( 'UTC' ) );
	return intval( $date_obj->format( 'U' ) );
}

function hf_strtotime_exceptional( $time_string, $from_timestamp = null ) {

	$original_timezone = date_default_timezone_get();
	date_default_timezone_set( 'UTC' );
	if ( null === $from_timestamp ) {
		$next_timestamp = strtotime( $time_string );
	} else {
		$next_timestamp = strtotime( $time_string, $from_timestamp );
	}
	date_default_timezone_set( $original_timezone );
	return $next_timestamp;
}

function hf_get_days_in_cycle( $period, $interval ) {

	switch ( $period ) {
		case 'day' :
			$days_in_cycle = $interval;
			break;
		case 'week' :
			$days_in_cycle = $interval * 7;
			break;
		case 'month' :
			$days_in_cycle = $interval * 30.4375;
			break;
		case 'year' :
			$days_in_cycle = $interval * 365.25;
			break;
	}

	return apply_filters( 'hf_get_days_in_cycle', $days_in_cycle, $period, $interval );
}


function hf_get_sites_timezone() {

	if ( class_exists( 'ActionScheduler_TimezoneHelper' ) ) {
		$local_timezone = ActionScheduler_TimezoneHelper::get_local_timezone();
	} else {
		$tzstring = get_option( 'timezone_string' );
		if ( empty( $tzstring ) ) {
			$gmt_offset = get_option( 'gmt_offset' );
			if ( 0 == $gmt_offset ) {
				$tzstring = 'UTC';
			} else {

				$gmt_offset *= HOUR_IN_SECONDS;
				$tzstring    = timezone_name_from_abbr( '', $gmt_offset );

				if ( false === $tzstring ) {
					$is_dst = date( 'I' );
					foreach ( timezone_abbreviations_list() as $abbr ) {
						foreach ( $abbr as $city ) {
							if ( $city['dst'] == $is_dst && $city['offset'] == $gmt_offset ) {
								$tzstring = $city['timezone_id'];
								break 2;
							}
						}
					}
				}

				if ( false === $tzstring ) {
					$tzstring = 'UTC';
				}
			}
		}

		$local_timezone = new DateTimeZone( $tzstring );
	}

	return $local_timezone;
}

function hf_get_datetime_from( $variable_date_type ) {

	try {
		if ( empty( $variable_date_type ) ) {
			$datetime = null;
		} elseif ( is_a( $variable_date_type, 'WC_DateTime' ) ) {
			$datetime = $variable_date_type;
		} elseif ( is_numeric( $variable_date_type ) ) {
			$datetime = new WC_DateTime( "@{$variable_date_type}", new DateTimeZone( 'UTC' ) );
			$datetime->setTimezone( new DateTimeZone( wc_timezone_string() ) );
		} else {
			$datetime = new WC_DateTime( $variable_date_type, new DateTimeZone( wc_timezone_string() ) );
		}
	} catch ( Exception $e ) {
		$datetime = null;
	}

	return $datetime;
}

function hf_get_datetime_utc_string( $datetime ) {
	$date = clone $datetime;
	$date->setTimezone( new DateTimeZone( 'UTC' ) );
	return $date->format( 'Y-m-d H:i:s' );
}

function hf_format_datetime( $date, $format = '' ) {

	if ( function_exists( 'wc_format_datetime' ) ) { // WC 3.0+
		$formatted_datetime = wc_format_datetime( $date, $format );
	} else {
		if ( ! $format ) {
			$format = wc_date_format();
		}
		if ( ! is_a( $date, 'WC_DateTime' ) ) {
			return '';
		}

		$formatted_datetime = $date->date_i18n( $format );
	}

	return $formatted_datetime;
}