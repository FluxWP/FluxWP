<?php
/**
 * Return types of the untyped Flux Certificates helpers the GraphQL services call.
 * PHPStan reads these instead of the sources, so the legacy files stay untouched.
 */

/** @return string */
function flxs_table( mixed $name ): string {}

/** @return string */
function flxs_now(): string {}

/** @return string */
function flxs_date( mixed $utc_mysql, mixed $format = null ): string {}

/** @return mixed */
function flxs_setting( mixed $key ): mixed {}

/** @return string */
function flxs_normalize_phone( mixed $raw ): string {}

/** @return string */
function flxs_format_phone( mixed $phone ): string {}

/** @return string */
function flxs_user_label( mixed $user_id ): string {}

/** @return bool */
function flxs_can_use(): bool {}

/** @return bool */
function flxs_can_manage(): bool {}

/** @return bool */
function flxs_can_bulk(): bool {}

/** @return bool */
function flxs_own_only(): bool {}

/** @return bool */
function flxs_own_ref(): bool {}

/** @return bool */
function flxs_scoped(): bool {}

/** @return string */
function flxs_scope_sql( mixed $alias = 'c' ): string {}

/** @return bool */
function flxs_can_see_certificate( mixed $cert ): bool {}

/** @return string */
function flxs_people_scope_sql( mixed $alias = 'p' ): string {}

/** @return bool */
function flxs_can_see_person( mixed $id ): bool {}

/** @return string */
function flxs_people_mine_sql( mixed $alias = 'p' ): string {}

/** @return array<int, mixed> */
function flxs_users_with_cap(): array {}

/** @return int */
function flxs_valid_ref_user( mixed $user_id ): int {}

/** @return array<string, string> */
function flxs_channels(): array {}

/** @return array<string, mixed>|null */
function flxs_get_certificate( mixed $id ): ?array {}

/** @return array<string, mixed>|null */
function flxs_get_certificate_by_code( mixed $code ): ?array {}

/** @return string */
function flxs_certificate_url( mixed $cert ): string {}

/** @return string */
function flxs_certificate_pdf_url( mixed $cert ): string {}

/** @return string */
function flxs_certificate_file_url( mixed $cert, mixed $type = 'image' ): string {}

/** @return string */
function flxs_certificate_file_path( mixed $cert, mixed $type = 'image' ): string {}

/**
 * @param array<string, mixed> $values
 * @return mixed
 */
function flxs_clean_values( mixed $template, array $values ) {}

/** @return mixed */
function flxs_store_certificate_file( mixed $cert, mixed $type, mixed $tmp_path, mixed $w = 0, mixed $h = 0 ) {}

/** @return bool */
function flxs_set_certificate_status( mixed $id, mixed $status ): bool {}

/** @return bool */
function flxs_delete_certificate( mixed $id ): bool {}

/**
 * @param array<string, mixed> $args
 * @return array{items: array<int, array<string, mixed>>, total: int}
 */
function flxs_query_certificates( array $args = array() ): array {}

/** @return string */
function flxs_certificate_name( mixed $cert, mixed $person = null ): string {}

/** @return array<string, mixed> */
function flxs_certificate_for_js( mixed $cert ): array {}

/** @return bool */
function flxs_user_can_edit_certificate( mixed $cert ): bool {}

/** @return string */
function flxs_ref_label( mixed $user_id ): string {}

/** @return bool */
function flxs_set_certificate_ref( mixed $id, mixed $ref_user_id ): bool {}

/**
 * @param array<string, mixed> $values
 * @return mixed
 */
function flxs_create_certificate( mixed $template_id, mixed $person_id, array $values, mixed $ref_user_id = 0, mixed $issued = '' ) {}

/**
 * @param array<string, mixed> $values
 * @return mixed
 */
function flxs_update_certificate( mixed $id, mixed $person_id, array $values, mixed $ref_user_id = null ) {}

/** @return array<string, mixed>|null */
function flxs_get_template( mixed $id ): ?array {}

/** @return array<int, array<string, mixed>> */
function flxs_list_templates( mixed $only_active = false ): array {}

/** @return array<string, mixed>|null */
function flxs_get_event( mixed $id ): ?array {}

/** @return array<int, array<string, mixed>> */
function flxs_get_events( mixed $status = '' ): array {}

/** @return string */
function flxs_event_label( mixed $id ): string {}

/** @return bool */
function flxs_valid_event( mixed $id, mixed $allow_closed = false ): bool {}

/** @return mixed */
function flxs_set_certificate_event( mixed $cert_id, mixed $event_id ) {}

/** @return array<int, int> */
function flxs_event_counts(): array {}

/** @return array<int, int> */
function flxs_ref_counts(): array {}

/** @return array<string, mixed>|null */
function flxs_get_person( mixed $id ): ?array {}

/** @return array<string, mixed>|null */
function flxs_find_person_by_phone( mixed $phone ): ?array {}

/** @return array<string, mixed>|null */
function flxs_find_person_by_email( mixed $email ): ?array {}

/**
 * @return mixed
 */
function flxs_upsert_person( mixed $name, mixed $phone_raw, mixed $email_raw, mixed $person_id = 0 ) {}

/** @return array<int, array<string, mixed>> */
function flxs_search_people( mixed $term, mixed $limit = 10, mixed $scope = false ): array {}

/** @return array<string, mixed>|null */
function flxs_person_public( mixed $p ): ?array {}

/** @return mixed */
function flxs_delete_person( mixed $id ) {}

/**
 * @param array<string, mixed> $m
 * @return mixed
 */
function flxs_enqueue( array $m ) {}

/**
 * @param array<int, string>        $channels
 * @param array<int|string, int>    $providers
 * @param array<string, mixed>      $overrides
 * @return array<string, mixed>
 */
function flxs_send_certificate( mixed $cert_id, array $channels, array $providers = array(), mixed $source = 'manual', mixed $batch_id = 0, array $overrides = array() ): array {}

/** @return void */
function flxs_kick_queue( mixed $when = 0 ): void {}

/**
 * @param array<string, mixed> $args
 * @return array{items: array<int, array<string, mixed>>, total: int}
 */
function flxs_query_messages( array $args = array() ): array {}

/** @return array<string, array<int, array<string, mixed>>> */
function flxs_provider_options_for_js(): array {}

/** @return mixed */
function flxs_resolve_provider( mixed $channel, mixed $provider_id = 0 ) {}

/** @return array<int, string> */
function flxs_renderer_scripts(): array {}

/** @return array<string, mixed> */
function flxs_renderer_config(): array {}

/** @return array<string, mixed> */
function flxs_page_data( mixed $cert ): array {}

/** @return string */
function flxs_lookup_message(): string {}

/** @return mixed*/
function flxs_rest_error( mixed $e, mixed $status = 400 ) {}

/** @return int */
function flxs_rest_pages( mixed $total, mixed $per ): int {}

/** @return void */
function flxs_save_recipient( mixed $name, mixed $phone_raw, mixed $email_raw ): void {}

/** @return string */
function flxs_first_name( mixed $full ): string {}
