<?php
/**
 * Flux AI GraphQL: the approval queue (actions waiting for the signed-in user and open change requests).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

flx_gql_type(
	'FlxAiApproval',
	array(
		'description' => __( 'An action the assistant prepared that waits for approval.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Approval::class,
			array(
				'chatId'    => array( 'Int!', __( 'Chat it belongs to.', 'flx-ai' ) ),
				'chatTitle' => array( 'String!', __( 'Chat title; empty for a new chat.', 'flx-ai' ) ),
				'callId'    => array( 'String!', __( 'Tool call id.', 'flx-ai' ) ),
				'tool'      => array( 'String!', __( 'Tool name.', 'flx-ai' ) ),
				'title'     => array( 'String!', __( 'Tool title for display.', 'flx-ai' ) ),
				'args'      => array( 'String!', __( 'The input as pretty-printed JSON.', 'flx-ai' ) ),
				'note'      => array( 'String!', __( 'What the assistant said about it.', 'flx-ai' ) ),
				'time'      => array( 'FlxDateTime!', __( 'When it was prepared.', 'flx-ai' ), 'ts', 'time' ),
				'taskId'    => array( 'Int!', __( 'Scheduled task it belongs to; 0 for a chat.', 'flx-ai' ) ),
				'taskTitle' => array( 'String!', __( 'Task title; empty for a chat.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiChangeFile',
	array(
		'description' => __( 'A file of a change request.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Change_File::class,
			array(
				'path' => array( 'String!', __( 'Workspace path.', 'flx-ai' ) ),
				'op'   => array( 'String!', __( 'add, edit or delete.', 'flx-ai' ) ),
				'add'  => array( 'Int!', __( 'Lines added.', 'flx-ai' ) ),
				'del'  => array( 'Int!', __( 'Lines removed.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiChange',
	array(
		'description' => __( 'An open workspace change request.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Change::class,
			array(
				'id'        => array( 'Int!', __( 'Change request number.', 'flx-ai' ) ),
				'title'     => array( 'String!', __( 'Title.', 'flx-ai' ) ),
				'chatId'    => array( 'Int!', __( 'Chat it came from.', 'flx-ai' ) ),
				'taskId'    => array( 'Int!', __( 'Scheduled task; 0 for a chat.', 'flx-ai' ) ),
				'taskTitle' => array( 'String!', __( 'Task title; empty for a chat.', 'flx-ai' ) ),
				'created'   => array( 'FlxDateTime!', __( 'When it was opened.', 'flx-ai' ), 'created', 'time' ),
				'files'     => array( '[FlxAiChangeFile!]!', __( 'Changed files.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiApprovalQueue',
	array(
		'description' => __( 'Everything that waits for the signed-in user.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Approval_Queue::class,
			array(
				'items'   => array( '[FlxAiApproval!]!', __( 'Actions waiting for approval, newest first.', 'flx-ai' ) ),
				'changes' => array( '[FlxAiChange!]!', __( 'Open change requests.', 'flx-ai' ) ),
				'count'   => array( 'Int!', __( 'Actions and change requests waiting.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_query(
	'flxAiApprovals',
	array(
		'description' => __( 'The signed-in user\'s approval queue.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => 'FlxAiApprovalQueue!',
		'resolve'     => static function (): FLXAI_Approval_Queue {
			return FLXAI_Approvals_Service::queue();
		},
	)
);

flx_gql_mutation(
	'flxAiApprovalDecide',
	array(
		'description' => __( 'Approves or rejects one action. With `args` it runs with the edited input. The assistant continues in the chat when it was the last action waiting.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'chatId' => array(
				'type'        => 'Int!',
				'description' => __( 'Chat.', 'flx-ai' ),
			),
			'callId' => array(
				'type'        => 'String!',
				'description' => __( 'Tool call id.', 'flx-ai' ),
			),
			'approve' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Approve (false rejects).', 'flx-ai' ),
			),
			'args'   => array(
				'type'        => 'String',
				'description' => __( 'Edited input as JSON; leave out to run it as prepared.', 'flx-ai' ),
			),
		),
		'output'      => array(
			'state' => array(
				'type'        => 'String',
				'description' => __( 'approval (more waiting), continue (the assistant is still working), done or busy.', 'flx-ai' ),
			),
			'queue' => array(
				'type'        => 'FlxAiApprovalQueue',
				'description' => __( 'The queue after the decision.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$args = array_key_exists( 'args', $input ) && null !== $input['args'] ? flx_gql_str( $input['args'] ) : null;
			$r    = FLXAI_Approvals_Service::decide( flx_gql_int( $input['chatId'] ?? 0 ), flx_gql_str( $input['callId'] ?? '' ), ! empty( $input['approve'] ), $args );
			return $r instanceof WP_Error ? $r : array( 'state' => $r->state, 'queue' => $r->queue );
		},
	)
);

flx_gql_mutation(
	'flxAiChangeDecide',
	array(
		'description' => __( 'Merges or rejects an open change request.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id'    => array(
				'type'        => 'Int!',
				'description' => __( 'Change request number.', 'flx-ai' ),
			),
			'merge' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Merge (false rejects).', 'flx-ai' ),
			),
		),
		'output'      => array(
			'queue' => array(
				'type'        => 'FlxAiApprovalQueue',
				'description' => __( 'The queue after the decision.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Approvals_Service::decide_change( flx_gql_int( $input['id'] ?? 0 ), ! empty( $input['merge'] ) );
			return $r instanceof WP_Error ? $r : array( 'queue' => $r );
		},
	)
);
