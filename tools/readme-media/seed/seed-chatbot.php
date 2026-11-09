<?php
/** Fictional local conversations; no provider requests or outgoing messages. */
require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
wp_set_current_user( 1 );
flxcb_update_settings( array( 'handoff_to' => 'inbox' ) );
if ( ! get_option( 'readme_chatbot_seeded' ) ) {
	foreach ( array( 'Alex Morgan', 'Deniz Kaya', 'Sam Lee' ) as $i => $name ) {
		list( $conversation, $token ) = flxcb_create_conversation( array( 'name' => $name, 'email' => 'demo' . $i . '@example.test', 'page_url' => home_url( '/contact/' ), 'page_title' => 'Contact', 'locale' => 'en' ) );
		flxcb_add_message( array( 'conv_id' => $conversation['id'], 'role' => 'visitor', 'body' => array( 'How can I get started?', 'Can I speak with your support team?', 'Where can I find my certificate?' )[ $i ] ) );
		flxcb_update_conversation( $conversation['id'], array( 'status' => 'queued' ) );
	}
	update_option( 'readme_chatbot_seeded', 1 );
}
echo "Chatbot demo conversations ready\n";
