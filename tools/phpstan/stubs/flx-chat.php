<?php
/**
 * Return types of the untyped Flux Chat helpers the GraphQL services call.
 * PHPStan reads these instead of the sources, so the legacy files stay untouched.
 *
 * @package FLX_Chat
 */

/**
 * Named here because a stub file cannot reference a class that exists only in a bootstrap file.
 * PHPStan merges this declaration with the WordPress class.
 */
class WP_Error {
	/** @return string */
	public function get_error_code() {}

	/** @return string */
	public function get_error_message() {}

	/** @param mixed $data */
	public function add_data( $data ): void {}
}

/** @return string */
function flxc_table( mixed $name ) {}

/** @return string */
function flxc_now() {}

/** @return int */
function flxc_rev() {}

/** @return array<string, mixed> */
function flxc_decode( mixed $json, mixed $default = array() ) {}

/** @return string */
function flxc_normalize_phone( mixed $raw ) {}

/** @return string */
function flxc_format_phone( mixed $phone ) {}

/** @return bool */
function flxc_can_use() {}

/** @return bool */
function flxc_can_manage() {}

/** @return list<array<string, mixed>> */
function flxc_agents() {}

/** @return string */
function flxc_user_name( mixed $user_id ) {}

/** @return mixed */
function flxc_setting( mixed $key ) {}

/** @return array<string, mixed>|null */
function flxc_get_chat( mixed $id ) {}

/** @return array<string, mixed>|null */
function flxc_get_message( mixed $id ) {}

/** @return array<string, mixed>|null */
function flxc_find_chat_by_phone( mixed $conn, mixed $phone ) {}

/** @return array<string, mixed>|null */
function flxc_get_connection( mixed $id ) {}

/** @return array<string, mixed>|null */
function flxc_default_connection() {}

/** @return array<string, mixed>|null */
function flxc_ensure_chat( mixed $conn, mixed $jid, mixed $data = array() ) {}

/** @return string */
function flxc_lookup_contact_name( mixed $phone ) {}

/**
* @return bool
 * @param array<mixed> $fields
 * @param array<mixed> $by
 */
function flxc_update_chat( mixed $id, array $fields, array $by = array() ) {}

/** @return string
 */
function flxc_chat_title( mixed $chat ) {}

/** @return bool */
function flxc_user_can_see_chat( mixed $chat, mixed $user_id = 0 ) {}

/**
 * Rows, or an int when count is set.
 *
 * @return list<array<string, mixed>>|int
 */
function flxc_query_chats( mixed $args = array() ) {}

/** @return int */
function flxc_unread_total() {}

/**
* @return list<array<string, mixed>>
 * @param array<mixed> $rows
 */
function flxc_chats_for_js( array $rows ) {}

/** @return array<string, mixed>
 */
function flxc_chat_for_js( mixed $r, mixed $last = null ) {}

/** @return array<string, mixed>|null */
function flxc_find_message_by_wa( mixed $conn, mixed $wa_id ) {}

/**
* @return int
 * @param array<mixed> $m
 */
function flxc_insert_message( array $m, mixed $chat = null ) {}

/**
* @return bool
 * @param array<mixed> $fields
 */
function flxc_update_message( mixed $id, array $fields ) {}

/** @return array<string, mixed>|WP_Error
 */
function flxc_apply_edit( mixed $m, mixed $body, mixed $user_id = 0 ) {}

/** @return array<string, mixed>|WP_Error */
function flxc_soft_delete_message( mixed $m, mixed $scope, mixed $user_id = 0 ) {}

/** @return int */
function flxc_revoke_limit_hours() {}

/** @return int */
function flxc_revoke_until( mixed $m ) {}

/** @return int */
function flxc_edit_until( mixed $m ) {}

/** @return list<array<string, mixed>> */
function flxc_chat_messages( mixed $chat_id, mixed $before_id = 0, mixed $limit = 40 ) {}

/** @return list<array<string, mixed>> */
function flxc_messages_since( mixed $chat_id, mixed $since, mixed $after_id = 0, mixed $limit = 200 ) {}

/** @return list<array<string, mixed>> */
function flxc_new_incoming_since( mixed $since ) {}

/**
 * @param list<array<string, mixed>> $rows
 * @return non-empty-list<array<string, mixed>>
 */
function flxc_messages_for_js( array $rows ) {}

/** @return string
 */
function flxc_chat_number( mixed $chat ) {}

/** @return string */
function flxc_fill_vars( mixed $text, mixed $chat, mixed $user_id = 0 ) {}

/**
* @return array<string, mixed>|WP_Error
 * @param array<mixed> $args
 */
function flxc_send( mixed $chat, array $args ) {}

/** @return array<string, mixed>|null
 */
function flxc_ai_active() {}

/** @return string|WP_Error */
function flxc_ai_suggest( mixed $chat, mixed $hint = '' ) {}

/** @return bool */
function flxc_flxb_on() {}

/** @return bool */
function flxc_can_record_donation() {}

/** @return bool */
function flxc_can_record_certificate() {}

/** @return array<string, mixed> */
function flxc_records_boot() {}

/**
* @return array<string, mixed>|WP_Error
 * @param array<mixed> $p
 */
function flxc_create_donation( array $p, mixed $chat = null ) {}

/**
* @return array<string, mixed>|WP_Error
 * @param array<mixed> $a
 */
function flxc_make_certificate( array $a ) {}

/**
* @return array<string, mixed>
 * @param array<mixed> $c
 */
function flxc_certificate_for_js( array $c ) {}

/** @return array<string, mixed>|WP_Error
 */
function flxc_attach_receipt( mixed $message_id, mixed $kind, mixed $ref_id, mixed $mark_paid = false, mixed $note = '' ) {}

/** @return array<string, mixed>|WP_Error */
function flxc_rest_attach_receipt( mixed $req ) {}

/** @return array<string, mixed>|WP_Error */
function flxc_rest_detach_receipt( mixed $req ) {}

/** @return array<string, mixed>|WP_Error */
function flxc_rest_chat_or_error( mixed $id ) {}

/** @return list<array<string, mixed>> */
function flxc_replies_for_js() {}

/** @return list<array<string, mixed>> */
function flxc_labels_for_js() {}

/** @return list<array<string, mixed>> */
function flxc_contact_sections( mixed $chat ) {}

/**
* @return int
 * @param array<mixed> $meta
 */
function flxc_system_message( mixed $chat, mixed $text, array $meta = array() ) {}

/** @return array<string, mixed>|WP_Error
 */
function flxc_upload_check( mixed $f, mixed $name, mixed $type ) {}

/** @return array<string, mixed>|WP_Error */
function flxc_upload_take( mixed $token ) {}

/**
* @return array<string, mixed>
 * @param array<mixed> $chat
 * @param array<mixed> $o
 */
function flxc_export_chat( array $chat, array $o ) {}

/**
* @return string
 * @param array<mixed> $c
 */
function flxc_export_md( array $c ) {}

/** @return int
 */
function flxc_clear_chat_history( mixed $chat_id, mixed $keep_receipts = true ) {}

/** @return array<string, mixed>|null */
function flxc_block_find( mixed $phone = '', mixed $email = '', mixed $jid = '' ) {}

/** @return array<string, mixed>|WP_Error */
function flxc_block_add( mixed $phone, mixed $email = '', mixed $reason = '', mixed $source = 'agent', mixed $chat = null ) {}

/** @return bool */
function flxc_block_remove( mixed $id ) {}

/** @return bool */
function flxc_block_chat( mixed $chat, mixed $reason, mixed $source ) {}

/** @return array<string, mixed> */
function flxc_block_for_js( mixed $r ) {}

/** @return bool */
function flxc_bot_enabled( mixed $reset = false ) {}

/** @return array<string, mixed> */
function flxc_bot_state( mixed $chat ) {}

/**
* @return bool
 * @param array<mixed> $state
 */
function flxc_bot_save_state( mixed $chat_id, array $state ) {}

/** @return int
 */
function flxc_bot_last_in_id( mixed $chat_id ) {}

/** @return bool */
function flxc_bot_queue( mixed $chat_id ) {}

/** @return int */
function flxc_bot_daily_limit() {}

/** @return bool */
function flxc_rem_on() {}

/** @return array<string, mixed>|null */
function flxc_rem_get( mixed $id ) {}

/** @return list<array<string, mixed>> */
function flxc_rem_for_chat( mixed $chat_id, mixed $status = 'active' ) {}

/**
* @return array<string, mixed>|WP_Error
 * @param array<mixed> $a
 * @param array<mixed> $base
 */
function flxc_rem_build( array $a, array $base = array() ) {}

/**
* @return array<string, mixed>|WP_Error
 * @param array<mixed> $r
 */
function flxc_rem_save( mixed $chat, array $r, mixed $id = 0, mixed $source = 'bot' ) {}

/** @return bool
 */
function flxc_rem_set_status( mixed $id, mixed $status ) {}

/** @return array<string, mixed> */
function flxc_rem_for_js( mixed $r ) {}

/** @return array<string, mixed> */
function flxc_rem_row( mixed $r ) {}

/** @return array<string, mixed> */
function flxc_mem_for_js( mixed $chat ) {}

/** @return array<string, mixed> */
function flxc_mem_who( mixed $chat ) {}

/** @return string */
function flxc_mem_key( mixed $k ) {}

/** @return string */
function flxc_mem_value( mixed $v ) {}

/** @return bool */
function flxc_mem_set( mixed $chat, mixed $k, mixed $v, mixed $source = 'bot', mixed $kind = 'fact', mixed $at = 0 ) {}

/** @return bool */
function flxc_mem_forget( mixed $chat, mixed $k = '' ) {}

/** @return bool */
function flxc_mem_log( mixed $chat, mixed $k, mixed $old, mixed $new, mixed $source ) {}

/** @return list<array<string, mixed>> */
function flxc_clog_for_js( mixed $chat_id, mixed $limit = 50 ) {}

/** @return list<array<string, mixed>> */
function flxc_channels_for_js() {}

/** @return list<array<string, mixed>> */
function flxc_connections_for_js() {}

/** @return array<string, mixed>|null */
function flxc_chat_usage_for_js( mixed $chat ) {}

/** @return array<string, mixed>|WP_Error */
function flxc_link_preview( mixed $url, mixed $fresh = false ) {}

/** @return array<string, mixed>|WP_Error */
function flxc_fetch_chat_history( mixed $chat, mixed $per = 50 ) {}

/** @return array<string, mixed> */
function flxc_apply_reaction( mixed $conn_id, mixed $target_wa, mixed $side, mixed $emoji ) {}

/** @return array<string, mixed> */
function flxc_evo_send_reaction( mixed $conn, mixed $remote_jid, mixed $from_me, mixed $wa_id, mixed $emoji ) {}

/** @return array<string, mixed> */
function flxc_evo_delete_for_everyone( mixed $conn, mixed $remote_jid, mixed $wa_id, mixed $participant = '' ) {}

/** @return array<string, mixed> */
function flxc_evo_edit_message( mixed $conn, mixed $remote_jid, mixed $wa_id, mixed $text ) {}

/**
 * @param array<mixed> $wa_ids Ids.
 * @return array<string, mixed>
 */
function flxc_evo_mark_read( mixed $conn, mixed $remote_jid, array $wa_ids ) {}

/** @return array<string, mixed> */
function flxc_evo_presence( mixed $conn, mixed $number, mixed $presence = 'composing', mixed $delay = 3000 ) {}

/**
 * @param array<mixed> $numbers Numbers.
 * @return array<string, mixed>|WP_Error
 */
function flxc_evo_check_numbers( mixed $conn, array $numbers ) {}

/** @return array<string, mixed>|WP_Error */
function flxc_staff_link_chat( mixed $chat, mixed $user_id ) {}

/** @return list<array<string, mixed>> */
function flxc_staff_link_users() {}

/** @return int */
function flxc_avatar_open_ttl() {}

/** @return string */
function flxc_avatar_url( mixed $chat ) {}

/** @return bool */
function flxc_avatar_check( mixed $chat, mixed $ttl = 0 ) {}

class FLXB_Donations {
	/**
	 * @param mixed $id Donation id.
	 * @param mixed $code Certificate code.
	 */
	public static function set_certificate( $id, $code ): void {}
}
