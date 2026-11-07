<?php
/** Real chat ownership, message lifecycle and concurrent-writer regression. No external provider is called. */
if ( ! defined( 'FLUX_NATIVE_QA' ) || ! FLUX_NATIVE_QA || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Isolated local QA required.' ); }
function aichat_assert( $yes, $message ) { if ( ! $yes ) { throw new RuntimeException( $message ); } }
function aichat_request( $method, $path = '', $body = array() ) { $r = new WP_REST_Request( $method, '/flx-ai/v1/chats' . $path ); if ( 'GET' === $method ) { $r->set_query_params( $body ); } else { $r->set_header( 'Content-Type', 'application/json' ); $r->set_body( wp_json_encode( $body ) ); } return rest_do_request( $r ); }
$original_user = get_current_user_id(); $original_settings = get_option( 'flxai_settings', null ); $editor = 0; $other_user = 0; $ids = array(); $other = null; $writes = array(); $prefix = 'qa-chat-' . bin2hex( random_bytes( 4 ) );
$network = static function () { return new WP_Error( 'qa_no_network', 'No network in chat regression.' ); }; add_filter( 'pre_http_request', $network, 1 );
$tools = static function ( $tools ) use ( $prefix, &$writes ) { foreach ( array( 'first', 'second' ) as $name ) { $tools[ $prefix . '_' . $name ] = array( 'title' => $name, 'description' => 'QA write', 'source' => 'qa-chat', 'readonly' => false, 'cap' => 'read', 'schema' => array( 'type' => 'object', 'properties' => new stdClass() ), 'run' => static function ( $args, $ctx ) use ( $name, &$writes ) { $writes[] = $name; if ( ! empty( $args['stop'] ) ) { flxai_agent_stop( flxai_chat_get( $ctx['chat_id'] ) ); } return array( 'qa' => $name ); } ); } return $tools; }; add_filter( 'flxai_tools', $tools, 90 );
try {
	global $wpdb;
	wp_set_current_user( 0 ); aichat_assert( in_array( aichat_request( 'GET' )->get_status(), array( 401,403 ), true ), 'Anonymous chat access allowed.' );
	$editor = wp_insert_user( array( 'user_login' => $prefix, 'user_pass' => wp_generate_password( 32 ), 'role' => 'editor' ) ); $other_user = wp_insert_user( array( 'user_login' => $prefix . '-other', 'user_pass' => wp_generate_password( 32 ), 'role' => 'editor' ) );
	aichat_assert( ! is_wp_error( $editor ) && ! is_wp_error( $other_user ), 'Fixture users failed.' );
	wp_set_current_user( get_user_by( 'login', 'fluxqa' )->ID );
	flxai_update_settings( array( 'chat_roles' => array( 'administrator','editor' ), 'model' => 'openrouter:qa/model', 'key_openrouter' => 'QA-CHAT-KEY', 'chat_widgets' => 1 ) );
	wp_set_current_user( $editor ); $c = flxai_chat_create(); $ids[] = $c['id']; $id = $c['id'];
	$c['messages'] = array( array( 'role' => 'user', 'content' => 'first', '_id' => 'u1' ), array( 'role' => 'assistant', 'content' => 'answer', '_id' => 'a1', '_raw' => array( 'secret' => 'RAW-PROVIDER' ), '_raw_model' => 'RAW-MODEL' ), array( 'role' => 'user', 'content' => 'second', '_id' => 'u2' ) ); flxai_chat_save( $c ); $stale = $c;
	wp_set_current_user( $other_user );
	foreach ( array( array('GET',''),array('POST',''),array('POST','/delete'),array('POST','/rewind'),array('POST','/stop'),array('POST','/step'),array('POST','/send'),array('POST','/decide') ) as $attempt ) { $r = aichat_request( $attempt[0], '/' . $id . $attempt[1], array( 'title' => 'foreign', 'text' => 'foreign', 'msg' => 'u1', 'decisions' => array() ) ); aichat_assert( 404 === $r->get_status(), 'Another user accessed/mutated a chat.' ); }
	aichat_assert( ! aichat_request( 'GET' )->get_data()['chats'], 'History exposed another owner.' );
	wp_set_current_user( $editor );
	$get = aichat_request( 'GET', '/' . $id )->get_data(); aichat_assert( false === strpos( wp_json_encode( $get ), 'RAW-PROVIDER' ) && false === strpos( wp_json_encode( $get ), 'RAW-MODEL' ), 'Public chat leaked provider internals.' );
	$partial = aichat_request( 'GET', '/' . $id, array( 'since' => 2 ) )->get_data(); aichat_assert( 2 === $partial['since'] && 3 === $partial['total'] && 1 === count( $partial['messages'] ), 'Incremental history contract failed.' );
	$rename = aichat_request( 'POST', '/' . $id, array( 'title' => '<b>İpek</b> planı', 'archived' => 1 ) ); aichat_assert( 200 === $rename->get_status() && 'İpek planı' === $rename->get_data()['title'] && 3 === count( $rename->get_data()['messages'] ), 'Rename/archive lost messages or sanitization.' );
	aichat_assert( 0 === count( aichat_request( 'GET' )->get_data()['chats'] ) && 1 === count( aichat_request( 'GET','',array('archived'=>1,'q'=>'İpek') )->get_data()['chats'] ), 'Archived/search filtering failed.' );
	aichat_assert( 403 === aichat_request( 'POST', '/' . $id, array( 'model' => 'qa/forbidden', 'title' => 'must-not-save' ) )->get_status() && 'İpek planı' === flxai_chat_get( $id )['title'], 'Non-manager selected model or partial patch saved.' );
	foreach ( array( array('title'=>array('bad')),array('archived'=>'false'),array('model'=>array('bad')) ) as $bad ) { aichat_assert( in_array( aichat_request('POST','/'.$id,$bad)->get_status(),array(400,403),true ), 'Invalid metadata accepted.' ); }
	// A genuinely separate DB connection owns the lock. It must block all competing writers.
	$other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ); aichat_assert( 1 === (int) $other->get_var( $other->prepare('SELECT GET_LOCK(%s,0)',flxai_agent_lock_name($id)) ), 'Could not hold fixture chat lock.' );
	foreach ( array( array('','title'),array('/delete',''),array('/rewind','msg'),array('/send','text') ) as $attempt ) { $r = aichat_request('POST','/'.$id.$attempt[0],array('title'=>'blocked','msg'=>'u1','text'=>'blocked'));aichat_assert(409===$r->get_status(),'Competing writer bypassed chat lock.'); }
	$busy = flxai_agent_step( flxai_chat_get($id) ); aichat_assert( 'busy' === $busy['state'], 'Competing step entered provider/tool execution.' );
	$before = flxai_chat_get($id); $stop = aichat_request('POST','/'.$id.'/stop'); aichat_assert(200===$stop->get_status()&&'idle'===$stop->get_data()['chat']['status']&&$before['messages']===flxai_chat_get($id)['messages'],'Stop waited on lock or overwrote message snapshot.');
	$other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)',flxai_agent_lock_name($id)));$other->close();$other=null;
	// Stale step/rewind input must use the latest saved snapshot after acquiring the lock.
	$fresh=flxai_chat_get($id);$fresh['messages'][]=array('role'=>'assistant','content'=>'latest','_id'=>'a2');flxai_chat_save($fresh);
	$step=flxai_agent_step($stale);aichat_assert('done'===$step['state']&&4===count($step['chat']['messages']),'Idle stale step executed or lost a newer message.');
	$rewind=aichat_request('POST','/'.$id.'/rewind',array('msg'=>'u1','mode'=>'keep'));aichat_assert(200===$rewind->get_status()&&2===count($rewind->get_data()['messages']),'Keep rewind did not preserve the complete first turn.');
	$edit=aichat_request('POST','/'.$id.'/rewind',array('msg'=>'u1','mode'=>'edit'));aichat_assert(200===$edit->get_status()&&0===count($edit->get_data()['messages']),'Edit rewind did not remove selected message and following history.');
	// Prepare two write calls without invoking a model or provider.
	$c=flxai_chat_get($id);$calls=array();foreach(array('first','second') as $name){$calls[]=array('id'=>$name,'type'=>'function','function'=>array('name'=>$prefix.'_'.$name,'arguments'=>'{}'));}$c['messages'][]=array('role'=>'assistant','content'=>'Review writes','tool_calls'=>$calls);$c['status']='approval';$c['state']['pending']=array('first','second');flxai_chat_save($c);$approval_stale=$c;
	aichat_assert(409===aichat_request('POST','/'.$id.'/decide',array('decisions'=>array('first'=>'approve')))->get_status()&&!$writes,'Incomplete/stale approval ran a write.');
	$mixed=aichat_request('POST','/'.$id.'/decide',array('decisions'=>array('first'=>'approve','second'=>'reject')));aichat_assert(200===$mixed->get_status()&&array('first')===$writes,'Per-call approve/reject did not preserve explicit choices.');
	$again=flxai_agent_decide($approval_stale,array('first'=>'approve','second'=>'approve'));aichat_assert(array('first')===$writes&&!$again['chat']['state']['pending'],'Replayed stale decision executed a write twice.');
	// Stop arriving during the first approved callback cancels the second callback.
	$c=flxai_chat_get($id);$calls[0]['function']['arguments']='{"stop":true}';$c['messages'][]=array('role'=>'assistant','content'=>'Review writes','tool_calls'=>$calls);$c['status']='approval';$c['state']['pending']=array('first','second');flxai_chat_save($c);$writes=array();
	$stopped=flxai_agent_decide($c,array('first'=>'approve','second'=>'approve'));aichat_assert(array('first')===$writes&&'done'===$stopped['state']&&'idle'===$stopped['chat']['status']&&!$stopped['chat']['state']['pending'],'Stop during decision failed to cancel remaining approved writes.');
	// Deleted snapshots cannot be resurrected by an old step/send/rewind request.
	aichat_assert(200===aichat_request('POST','/'.$id.'/delete')->get_status(),'Own chat deletion failed.');$gone=flxai_agent_step($c);aichat_assert(is_wp_error($gone)&&404===$gone->get_error_data()['status']&&!flxai_chat_get($id),'Stale step recreated a deleted conversation.');
	aichat_assert(flxai_agent_lock($id),'Chat lock leaked after error/return.');flxai_agent_unlock($id);
	echo "PASS: chat ownership, incremental history, raw-field isolation, rename/archive/search, manager models, validation, concurrent writers, nonblocking Stop, stale snapshots, rewind/edit, mixed tool decisions and duplicate/replayed-write protection.\n";
} finally {
	if($other){foreach($ids as $id){$other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)',flxai_agent_lock_name($id)));}$other->close();}
	global $wpdb;foreach($ids as $id){flxai_agent_unlock($id);$wpdb->delete(flxai_table('chats'),array('id'=>$id));}
	if(null===$original_settings){delete_option('flxai_settings');}else{update_option('flxai_settings',$original_settings);}$GLOBALS['flxai_settings_cache']=null;wp_set_current_user($original_user);remove_filter('pre_http_request',$network,1);remove_filter('flxai_tools',$tools,90);
	require_once ABSPATH.'wp-admin/includes/user.php';foreach(array($editor,$other_user) as $user){if($user&&!is_wp_error($user))wp_delete_user($user);}
}
