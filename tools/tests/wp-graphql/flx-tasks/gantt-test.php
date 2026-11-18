<?php
/**
 * Flux Tasks Gantt: task dependencies (deps) and the gantt view type. Loaded by contract-test.php
 * ($list, helpers come from there).
 */

echo "Flux Tasks Gantt: dependencies, view\n";

$mk = static function ( string $title, string $start, string $due ) use ( $list ): int {
	return (int) ( flxt_rest( 'POST', '/tasks', array( 'list' => $list, 'title' => $title, 'start' => $start, 'due' => $due ) )->get_data()['task']['id'] ?? 0 );
};
$a = $mk( 'Design', '2026-01-05', '2026-01-09' );
$b = $mk( 'Build', '2026-01-12', '2026-01-16' );
$c = $mk( 'Ship', '2026-01-19', '2026-01-19' );
flxt_ok( $a && $b && $c, 'gantt: three dated tasks' );

$res = flxt_rest( 'PATCH', '/tasks/' . $b, array( 'deps' => array( $a, $a, $b, 999999 ) ) );
flxt_same( array( 200, array( $a ) ), array( $res->get_status(), $res->get_data()['task']['deps'] ?? null ), 'deps: self, duplicates and unknown tasks are dropped' );
flxt_rest( 'PATCH', '/tasks/' . $c, array( 'deps' => array( $b ) ) );
$res = flxt_rest( 'PATCH', '/tasks/' . $a, array( 'deps' => array( $c ) ) );
flxt_same( array( 400, 'flxg_dep_cycle' ), array( $res->get_status(), $res->as_error()->get_error_code() ), 'deps: a loop (a→c→b→a) is refused' );
$res = flxt_rest( 'GET', '/tasks/' . $b );
flxt_same( array( $a ), $res->get_data()['task']['deps'] ?? null, 'deps: task detail carries deps' );
$acts = wp_list_pluck( (array) ( $res->get_data()['task']['activity'] ?? array() ), 'action' );
flxt_ok( in_array( 'dep', $acts, true ), 'deps: adding is logged' );

$res = flxt_rest( 'PATCH', '/tasks/' . $b, array( 'deps' => array() ) );
flxt_same( array(), $res->get_data()['task']['deps'] ?? null, 'deps: cleared' );
flxt_rest( 'PATCH', '/tasks/' . $b, array( 'deps' => array( $a ) ) );
flxt_rest( 'DELETE', '/tasks/' . $a );
$res = flxt_rest( 'GET', '/tasks/' . $b );
flxt_same( array(), $res->get_data()['task']['deps'] ?? null, 'deps: deleting the blocker removes the link' );

// Görünüm: gantt türü ve ayarları temizlenir.
$res = flxt_rest( 'POST', '/views', array( 'scope' => 'list', 'scope_id' => $list, 'name' => 'Timeline', 'type' => 'gantt', 'config' => array( 'gantt' => array( 'zoom' => 'month', 'color' => 'nope', 'cols' => array( 'due', 'evil' ), 'side' => 5000, 'resched' => true ) ) ) );
$v   = $res->get_data()['view'] ?? array();
flxt_same(
	array( 'gantt', 'month', 'status', array( 'due' ), 720, true, true ),
	array( $v['type'] ?? null, $v['config']['gantt']['zoom'] ?? null, $v['config']['gantt']['color'] ?? null, $v['config']['gantt']['cols'] ?? null, $v['config']['gantt']['side'] ?? null, $v['config']['gantt']['resched'] ?? null, $v['config']['gantt']['deps'] ?? null ),
	'view: gantt type and config are cleaned'
);
