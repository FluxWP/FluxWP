<?php
/**
 * Return types of the untyped Flux Ads helpers and legacy classes the GraphQL services call.
 * PHPStan reads these instead of the sources, so the legacy files stay untouched.
 */

/** @return string */
function flxads_table( mixed $name ) {}
/** @return array<string, mixed> */
function flxads_settings() {}
/**
 * @param array<string, mixed> $in
 * @return array<string, mixed>
 */
function flxads_clean_settings( array $in, mixed $partial = false ) {}
/** @return string */
function flxads_secret( mixed $key ) {}
/** @return string */
function flxads_mask( mixed $plain ) {}
/** @return array<string, string> */
function flxads_platforms() {}
/** @return array<string, string> */
function flxads_channel_labels() {}
/** @return string */
function flxads_channel_label( mixed $ch ) {}
/** @return bool */
function flxads_can( mixed $manage = false ) {}
/** @return string */
function flxads_money( mixed $amount, mixed $currency = '' ) {}
/** @return string */
function flxads_date( mixed $gmt, mixed $format = 'datetime' ) {}
/** @return string */
function flxads_ago( mixed $gmt ) {}

class FLXADS_Report {
	/** @return string */
	public static function base_currency() {}
	/** @return array<string, string> */
	public static function dimensions() {}
}
class FLXADS_Manager {
	/** @return string */
	public static function currency() {}
	/** @return array<string, string> */
	public static function ranges() {}
	/** @return array<string, string> */
	public static function statuses() {}
	/** @return array<int|string, mixed> */
	public static function history( mixed $limit = 20 ) {}
}
class FLXADS_Studio {
	/** @return array<string, string> */
	public static function statuses() {}
	/** @return array<string, string> */
	public static function objectives() {}
	/** @return array<string, string> */
	public static function ctas() {}
}
class FLXADS_Optimizer {
	/** @return array<string, string> */
	public static function types() {}
	/** @return array<string, string> */
	public static function statuses() {}
	/** @return array<int|string, mixed> */
	public static function open() {}
}
class FLXADS_Experiments {
	/** @return array<string, string> */
	public static function types() {}
}
class FLXADS_Audiences {
	/** @return array<string, string> */
	public static function sources() {}
	/** @return array<string, string> */
	public static function purposes() {}
}
class FLXADS_Spend {
	/** @return array<string, mixed> */
	public static function totals( mixed $from, mixed $to, mixed $channel = '' ) {}
}
class FLXADS_Send {
	/** @return array<string, mixed> */
	public static function counts() {}
}
