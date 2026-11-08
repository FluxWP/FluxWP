<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sertifika listesini Excel'e (xlsx) aktarma. Listede seçili filtrelerin tamamı uygulanır,
 * sayfalama uygulanmaz. Personel yalnız kendi oluşturduklarını alır (listeyle aynı kural).
 */

add_action( 'admin_post_flxs_cert_export', 'flxs_handle_cert_export' );

/** Sertifika listesindeki filtreler (liste ve dışa aktarma ortak kullanır). */
function flxs_cert_filter_args() {
	return array(
		'search'      => flxs_get_param( 's' ),
		'template_id' => (int) flxs_get_param( 'template' ),
		'status'      => flxs_get_param( 'status' ),
		'created_by'  => flxs_scoped() ? 0 : (int) flxs_get_param( 'by' ),
		'scope'       => true,
		'person_id'   => (int) flxs_get_param( 'person' ),
		'ref_user_id' => flxs_get_param( 'ref' ),
		'event_id'    => flxs_get_param( 'event' ),
	);
}

/** Geçerli filtrelerle Excel indirme adresi. */
function flxs_cert_export_url() {
	$keep = array();
	foreach ( array( 's', 'template', 'status', 'by', 'person', 'ref', 'event' ) as $k ) {
		$v = flxs_get_param( $k );
		if ( '' !== $v ) {
			$keep[ $k ] = $v;
		}
	}
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'flxs_cert_export' ), $keep ), admin_url( 'admin-post.php' ) ), 'flxs_cert_export' );
}

function flxs_handle_cert_export() {
	check_admin_referer( 'flxs_cert_export' );
	if ( ! flxs_can_use() ) {
		wp_die( esc_html__( 'Not allowed', 'flx-certificates' ) );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore
	}
	$q = flxs_query_certificates( array_merge( flxs_cert_filter_args(), array( 'per_page' => 0 ) ) );

	$events = array();
	foreach ( flxs_get_events() as $e ) {
		$events[ (int) $e['id'] ] = $e['name'];
	}
	$status = array(
		'active'  => __( 'Valid', 'flx-certificates' ),
		'revoked' => __( 'Revoked', 'flx-certificates' ),
	);
	$msgs = array();
	foreach ( array_chunk( wp_list_pluck( $q['items'], 'id' ), 500 ) as $ids ) {
		$msgs += flxs_cert_message_summary( $ids );
	}
	$users = array();
	$user  = function ( $id ) use ( &$users ) {
		$id = (int) $id;
		if ( ! isset( $users[ $id ] ) ) {
			$users[ $id ] = $id ? flxs_user_label( $id ) : '';
		}
		return $users[ $id ];
	};

	$head = array(
		array( __( 'Certificate no.', 'flx-certificates' ), 16 ),
		array( __( 'Date', 'flx-certificates' ), 17, 'date' ),
		array( __( 'Full name', 'flx-certificates' ), 28 ),
		array( __( 'Phone', 'flx-certificates' ), 17 ),
		array( __( 'Email', 'flx-certificates' ), 28 ),
		array( __( 'Event', 'flx-certificates' ), 26 ),
		array( __( 'Template', 'flx-certificates' ), 22 ),
		array( __( 'Referrer', 'flx-certificates' ), 20 ),
		array( __( 'Created by', 'flx-certificates' ), 20 ),
		array( __( 'Status', 'flx-certificates' ), 10 ),
		array( __( 'Views', 'flx-certificates' ), 13, 'int' ),
		array( __( 'Messages sent', 'flx-certificates' ), 26 ),
		array( __( 'Certificate link', 'flx-certificates' ), 48 ),
	);
	$rows = array();
	foreach ( $q['items'] as $c ) {
		$sent = array();
		foreach ( (array) ( $msgs[ $c['id'] ] ?? array() ) as $ch => $st ) {
			if ( ! empty( $st['sent'] ) ) {
				$sent[] = flxs_channel_label( $ch ) . ( $st['sent'] > 1 ? ' ×' . (int) $st['sent'] : '' );
			}
		}
		$rows[] = array(
			$c['number'],
			$c['created_at'],
			flxs_certificate_name( $c, array( 'name' => $c['person_name'] ) ),
			$c['person_phone'] ? flxs_format_phone( $c['person_phone'] ) : '',
			(string) $c['person_email'],
			$events[ (int) $c['event_id'] ] ?? '',
			(string) $c['template_name'],
			$user( $c['ref_user_id'] ),
			$user( $c['created_by'] ),
			$status[ $c['status'] ] ?? $c['status'],
			(int) $c['views'],
			implode( ', ', $sent ),
			flxs_certificate_url( $c ),
		);
	}

	/* translators: file name prefix of the certificate Excel export */
	$name = sanitize_file_name( _x( 'certificates', 'context_e363ab036dc9', 'flx-certificates' ) . '-' . wp_date( 'Y-m-d-Hi' ) . '.xlsx' );
	$file = wp_tempnam( $name );
	$ok   = flxs_xlsx_write( $file, __( 'Certificates', 'flx-certificates' ), $head, $rows );
	if ( is_wp_error( $ok ) ) {
		wp_delete_file( $file );
		wp_die( esc_html( $ok->get_error_message() ) );
	}
	flxs_log( 'certificates_exported', 'certificate', 0, (string) count( $rows ), array( 'filters' => array_filter( flxs_cert_filter_args() ) ) );
	nocache_headers();
	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	header( 'Content-Length: ' . filesize( $file ) );
	readfile( $file ); // phpcs:ignore
	wp_delete_file( $file );
	exit;
}

/**
 * Tek sayfalık basit xlsx yazar (ZipArchive). $head: [başlık, genişlik, tür?] tür: date (UTC mysql) | int.
 * Başlık satırı kalın ve sabit, otomatik filtre açık.
 *
 * @return true|WP_Error
 */
function flxs_xlsx_write( $file, $sheet, $head, $rows ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'flxs_zip', __( 'The server has no ZipArchive (PHP zip extension); the Excel file could not be created.', 'flx-certificates' ) );
	}
	$x   = function ( $s ) {
		// XML'de geçersiz kontrol karakterlerini at.
		return htmlspecialchars( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $s ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
	};
	$col = function ( $i ) {
		$s = '';
		for ( $i++; $i > 0; $i = intdiv( $i - 1, 26 ) ) {
			$s = chr( 65 + ( $i - 1 ) % 26 ) . $s;
		}
		return $s;
	};
	$tz   = wp_timezone();
	$last = $col( count( $head ) - 1 );

	$cols = '';
	foreach ( $head as $i => $h ) {
		$cols .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . (float) $h[1] . '" customWidth="1"/>';
	}
	$data = '<row r="1">';
	foreach ( $head as $i => $h ) {
		$data .= '<c r="' . $col( $i ) . '1" s="1" t="inlineStr"><is><t>' . $x( $h[0] ) . '</t></is></c>';
	}
	$data .= '</row>';
	foreach ( array_values( $rows ) as $r => $row ) {
		$n     = $r + 2;
		$data .= '<row r="' . $n . '">';
		foreach ( array_values( $row ) as $i => $v ) {
			$ref  = $col( $i ) . $n;
			$type = $head[ $i ][2] ?? '';
			if ( 'date' === $type && $v && '0000-00-00 00:00:00' !== $v ) {
				$d     = new DateTime( $v, new DateTimeZone( 'UTC' ) );
				$d->setTimezone( $tz );
				$data .= '<c r="' . $ref . '" s="2"><v>' . sprintf( '%.10F', ( $d->getTimestamp() + $d->getOffset() ) / 86400 + 25569 ) . '</v></c>';
			} elseif ( 'int' === $type ) {
				$data .= '<c r="' . $ref . '"><v>' . (int) $v . '</v></c>';
			} elseif ( '' !== (string) $v ) {
				$data .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $x( $v ) . '</t></is></c>';
			}
		}
		$data .= '</row>';
	}
	$end = count( $rows ) + 1;

	$parts = array(
		'[Content_Types].xml'        => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
		'_rels/.rels'                => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
		'xl/workbook.xml'            => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $x( mb_substr( str_replace( array( '\\', '/', '?', '*', '[', ']', ':' ), '', $sheet ), 0, 31 ) ) . '" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $x( mb_substr( str_replace( array( '\\', '/', '?', '*', '[', ']', ':', "'" ), '', $sheet ), 0, 31 ) ) . '\'!$A$1:$' . $last . '$' . $end . '</definedName></definedNames></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
		'xl/styles.xml'              => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE6F0E8"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf numFmtId="22" applyNumberFormat="1"/></cellXfs></styleSheet>',
		'xl/worksheets/sheet1.xml'   => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:' . $last . $end . '"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>' . $cols . '</cols><sheetData>' . $data . '</sheetData><autoFilter ref="A1:' . $last . $end . '"/></worksheet>',
	);
	$zip = new ZipArchive();
	if ( true !== $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		return new WP_Error( 'flxs_zip', __( 'The Excel file could not be created.', 'flx-certificates' ) );
	}
	foreach ( $parts as $path => $xml ) {
		$zip->addFromString( $path, $xml );
	}
	$zip->close();
	return true;
}
