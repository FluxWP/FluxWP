<?php
/**
 * Flux AI GraphQL: scheduled tasks.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

flx_gql_type(
	'FlxAiTaskSchedule',
	array(
		'description' => __( 'When a task repeats.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Task_Schedule::class,
			array(
				'freq'  => array( 'String!', __( 'daily, weekdays, weekly, monthly, hourly or once.', 'flx-ai' ) ),
				'time'  => array( 'String!', __( 'Time of day, HH:MM in the site time zone.', 'flx-ai' ) ),
				'days'  => array( '[Int!]!', __( 'Weekdays for weekly tasks, 1 (Monday) to 7 (Sunday).', 'flx-ai' ) ),
				'day'   => array( 'Int!', __( 'Day of the month for monthly tasks.', 'flx-ai' ) ),
				'every' => array( 'Int!', __( 'Hours between runs for hourly tasks.', 'flx-ai' ) ),
				'at'    => array( 'String!', __( 'Date and time of a one-time task (Y-m-d H:i).', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiRecipient',
	array(
		'description' => __( 'A person, address, number or WhatsApp group that can be notified.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Task_Recipient::class,
			array(
				'kind'     => array( 'String!', __( 'contact, user, email, phone or group.', 'flx-ai' ) ),
				'id'       => array( 'Int!', __( 'Contact or user ID; 0 for the other kinds.', 'flx-ai' ) ),
				'name'     => array( 'String!', __( 'Name for display.', 'flx-ai' ) ),
				'email'    => array( 'String!', __( 'E-mail address.', 'flx-ai' ) ),
				'phone'    => array( 'String!', __( 'Phone number, digits only.', 'flx-ai' ) ),
				'jid'      => array( 'String!', __( 'WhatsApp group address.', 'flx-ai' ) ),
				'sub'      => array( 'String!', __( 'What kind of recipient this is, for display.', 'flx-ai' ) ),
				'channels' => array( '[String!]!', __( 'Channels chosen for this recipient: email, sms, whatsapp.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiTaskNotify',
	array(
		'description' => __( 'How a task tells people about its runs.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Task_Notify::class,
			array(
				'mode'     => array( 'String!', __( 'none, important or always.', 'flx-ai' ) ),
				'email'    => array( 'Boolean!', __( 'Also e-mail the owner.', 'flx-ai' ) ),
				'panel'    => array( 'Boolean!', __( 'Show in the admin panel (menu badge).', 'flx-ai' ) ),
				'whatsapp' => array( 'String!', __( 'WhatsApp number of the owner, digits only; empty for none.', 'flx-ai' ) ),
				'promo'    => array( 'Boolean!', __( 'Promotional content: skip people who opted out and add an opt-out line.', 'flx-ai' ) ),
				'to'       => array( '[FlxAiRecipient!]!', __( 'More recipients.', 'flx-ai' ) ),
				'label'    => array( 'String!', __( 'Short summary of who is notified.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiTaskWorkspace',
	array(
		'description' => __( 'The files a task may touch.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Task_Workspace::class,
			array(
				'dir'     => array( 'String!', __( 'The task\'s own folder.', 'flx-ai' ) ),
				'access'  => array( 'String!', __( 'workspace (own folder and the allowed ones) or site (can read all site files).', 'flx-ai' ) ),
				'folders' => array( '[String!]!', __( 'Allowed folders, relative to the site root.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiTask',
	array(
		'description' => __( 'A scheduled task the assistant runs on its own.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Task::class,
			array(
				'id'            => array( 'Int!', __( 'Task ID.', 'flx-ai' ) ),
				'title'         => array( 'String!', __( 'Name.', 'flx-ai' ) ),
				'prompt'        => array( 'String!', __( 'Instructions.', 'flx-ai' ) ),
				'schedule'      => array( 'FlxAiTaskSchedule!', __( 'When it repeats.', 'flx-ai' ) ),
				'scheduleLabel' => array( 'String!', __( 'The schedule in words.', 'flx-ai' ), 'schedule_label' ),
				'status'        => array( 'String!', __( 'active, paused or done.', 'flx-ai' ) ),
				'nextRun'       => array( 'FlxDateTime', __( 'Next run; null when paused or finished.', 'flx-ai' ), 'next_run', 'time' ),
				'lastRun'       => array( 'FlxDateTime', __( 'Last run; null before the first.', 'flx-ai' ), 'last_run', 'time' ),
				'lastState'     => array( 'String!', __( 'ok, running, approval, waiting or error; empty before the first run.', 'flx-ai' ), 'last_state' ),
				'lastError'     => array( 'String!', __( 'Error of the last run.', 'flx-ai' ), 'last_error' ),
				'lastOutput'    => array( 'String!', __( 'Report of the last run (Markdown).', 'flx-ai' ), 'last_output' ),
				'runs'          => array( 'Int!', __( 'Number of runs.', 'flx-ai' ) ),
				'cost'          => array( 'Float!', __( 'Cost of the remembered runs in USD.', 'flx-ai' ) ),
				'avgMs'         => array( 'Int!', __( 'Average duration of the remembered runs, milliseconds.', 'flx-ai' ), 'avg_ms' ),
				'chatId'        => array( 'Int!', __( 'The task\'s own chat; 0 before the first run.', 'flx-ai' ), 'chat_id' ),
				'owner'         => array( 'String!', __( 'Name of the user the task runs as.', 'flx-ai' ) ),
				'mine'          => array( 'Boolean!', __( 'The signed-in user owns it.', 'flx-ai' ) ),
				'skill'         => array( 'String!', __( 'Skill slug; empty for none.', 'flx-ai' ) ),
				'unread'        => array( 'Boolean!', __( 'A new report the signed-in user has not opened.', 'flx-ai' ) ),
				'notify'        => array( 'FlxAiTaskNotify!', __( 'Who is told about the runs.', 'flx-ai' ) ),
				'workspace'     => array( 'FlxAiTaskWorkspace', __( 'Files; null when Flux Files is not installed.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiTaskSkill',
	array(
		'description' => __( 'A skill a task can use.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Task_Skill::class,
			array(
				'slug'   => array( 'String!', __( 'Skill slug.', 'flx-ai' ) ),
				'name'   => array( 'String!', __( 'Name.', 'flx-ai' ) ),
				'active' => array( 'Boolean!', __( 'The skill is on.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiNotifyChannels',
	array(
		'description' => __( 'Which channels can carry a notification.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Task_Channels::class,
			array(
				'email'    => array( 'Boolean!', __( 'E-mail.', 'flx-ai' ) ),
				'sms'      => array( 'Boolean!', __( 'SMS (needs a Flux Mail connection).', 'flx-ai' ) ),
				'whatsapp' => array( 'Boolean!', __( 'WhatsApp (needs a Flux Mail connection).', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiTaskOverview',
	array(
		'description' => __( 'The scheduled tasks page: tasks and what the form needs.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Task_Overview::class,
			array(
				'tasks'          => array( '[FlxAiTask!]!', __( 'Tasks, active first.', 'flx-ai' ) ),
				'enabled'        => array( 'Boolean!', __( 'The task system is on.', 'flx-ai' ) ),
				'nextRun'        => array( 'FlxDateTime', __( 'Next run of any active task.', 'flx-ai' ), 'next_run', 'time' ),
				'awaiting'       => array( 'Int!', __( 'Actions waiting for approval.', 'flx-ai' ) ),
				'lastCheck'      => array( 'FlxDateTime', __( 'When the schedule was last checked.', 'flx-ai' ), 'last_check', 'time' ),
				'workspaceReady' => array( 'Boolean!', __( 'Flux Files is installed, so tasks can have a folder.', 'flx-ai' ), 'workspace_ready' ),
				'whatsappReady'  => array( 'Boolean!', __( 'Flux Mail can send WhatsApp.', 'flx-ai' ), 'whatsapp_ready' ),
				'ownerEmail'     => array( 'String!', __( 'The signed-in user\'s e-mail address.', 'flx-ai' ), 'owner_email' ),
				'timezone'       => array( 'String!', __( 'Site time zone.', 'flx-ai' ) ),
				'skills'         => array( '[FlxAiTaskSkill!]!', __( 'Skills a task can use.', 'flx-ai' ) ),
				'channels'       => array( 'FlxAiNotifyChannels!', __( 'Channels that can carry a notification.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiRecipientSearch',
	array(
		'description' => __( 'Recipients that match a search.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Task_Search::class,
			array(
				'items'    => array( '[FlxAiRecipient!]!', __( 'Matches: Flux CRM contacts, site users, WhatsApp groups and typed addresses or numbers.', 'flx-ai' ) ),
				'channels' => array( 'FlxAiNotifyChannels!', __( 'Channels that can carry a notification.', 'flx-ai' ) ),
			)
		),
	)
);

/** Inputs. */
flx_gql_input(
	'FlxAiTaskScheduleInput',
	array(
		'description' => __( 'When a task repeats. Fields you leave out keep their value.', 'flx-ai' ),
		'fields'      => array(
			'freq'  => array( 'type' => 'String', 'description' => __( 'daily, weekdays, weekly, monthly, hourly or once.', 'flx-ai' ) ),
			'time'  => array( 'type' => 'String', 'description' => __( 'Time of day, HH:MM.', 'flx-ai' ) ),
			'days'  => array( 'type' => '[Int!]', 'description' => __( 'Weekdays for weekly tasks, 1 (Monday) to 7 (Sunday).', 'flx-ai' ) ),
			'day'   => array( 'type' => 'Int', 'description' => __( 'Day of the month.', 'flx-ai' ) ),
			'every' => array( 'type' => 'Int', 'description' => __( 'Hours between runs.', 'flx-ai' ) ),
			'at'    => array( 'type' => 'String', 'description' => __( 'Date and time of a one-time task (Y-m-d H:i or Y-m-dTH:i).', 'flx-ai' ) ),
		),
	)
);

flx_gql_input(
	'FlxAiRecipientInput',
	array(
		'description' => __( 'A recipient as FlxAiRecipient returned it.', 'flx-ai' ),
		'fields'      => array(
			'kind'     => array( 'type' => 'String!', 'description' => __( 'contact, user, email, phone or group.', 'flx-ai' ) ),
			'id'       => array( 'type' => 'Int', 'description' => __( 'Contact or user ID.', 'flx-ai' ) ),
			'name'     => array( 'type' => 'String', 'description' => __( 'Name for display.', 'flx-ai' ) ),
			'email'    => array( 'type' => 'String', 'description' => __( 'E-mail address.', 'flx-ai' ) ),
			'phone'    => array( 'type' => 'String', 'description' => __( 'Phone number.', 'flx-ai' ) ),
			'jid'      => array( 'type' => 'String', 'description' => __( 'WhatsApp group address.', 'flx-ai' ) ),
			'channels' => array( 'type' => '[String!]', 'description' => __( 'Channels to use: email, sms, whatsapp.', 'flx-ai' ) ),
		),
	)
);

flx_gql_input(
	'FlxAiTaskNotifyInput',
	array(
		'description' => __( 'How a task tells people about its runs. Fields you leave out keep their value.', 'flx-ai' ),
		'fields'      => array(
			'mode'     => array( 'type' => 'String', 'description' => __( 'none, important or always.', 'flx-ai' ) ),
			'email'    => array( 'type' => 'Boolean', 'description' => __( 'Also e-mail the owner.', 'flx-ai' ) ),
			'panel'    => array( 'type' => 'Boolean', 'description' => __( 'Show in the admin panel.', 'flx-ai' ) ),
			'whatsapp' => array( 'type' => 'String', 'description' => __( 'Owner\'s WhatsApp number; empty for none.', 'flx-ai' ) ),
			'promo'    => array( 'type' => 'Boolean', 'description' => __( 'Promotional content.', 'flx-ai' ) ),
			'to'       => array( 'type' => '[FlxAiRecipientInput!]', 'description' => __( 'The full list of more recipients; it replaces the old one.', 'flx-ai' ) ),
		),
	)
);

flx_gql_input(
	'FlxAiTaskWorkspaceInput',
	array(
		'description' => __( 'The files a task may touch.', 'flx-ai' ),
		'fields'      => array(
			'access'  => array( 'type' => 'String', 'description' => __( 'workspace or site.', 'flx-ai' ) ),
			'folders' => array( 'type' => '[String!]', 'description' => __( 'Allowed folders, relative to the site root.', 'flx-ai' ) ),
		),
	)
);

flx_gql_query(
	'flxAiTasks',
	array(
		'description' => __( 'Scheduled tasks and what the task form needs.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => 'FlxAiTaskOverview!',
		'resolve'     => static function (): FLXAI_Task_Overview {
			return FLXAI_Tasks_Service::overview();
		},
	)
);

flx_gql_query(
	'flxAiRecipients',
	array(
		'description' => __( 'Searches people, addresses, numbers and WhatsApp groups to notify.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => 'FlxAiRecipientSearch!',
		'args'        => array(
			'search' => array(
				'type'        => 'String',
				'description' => __( 'Part of a name, e-mail address, phone number or group name.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXAI_Task_Search {
			return FLXAI_Tasks_Service::search( flx_gql_str( $args['search'] ?? '' ) );
		},
	)
);

$flxai_task_fields = array(
	'title'     => array( 'type' => 'String', 'description' => __( 'Name; empty takes the first words of the instructions.', 'flx-ai' ) ),
	'prompt'    => array( 'type' => 'String', 'description' => __( 'Instructions, as if sent in the chat.', 'flx-ai' ) ),
	'active'    => array( 'type' => 'Boolean', 'description' => __( 'The task runs (false pauses it).', 'flx-ai' ) ),
	'skill'     => array( 'type' => 'String', 'description' => __( 'Skill slug; empty for none.', 'flx-ai' ) ),
	'schedule'  => array( 'type' => 'FlxAiTaskScheduleInput', 'description' => __( 'When it repeats.', 'flx-ai' ) ),
	'notify'    => array( 'type' => 'FlxAiTaskNotifyInput', 'description' => __( 'Who is told about the runs.', 'flx-ai' ) ),
	'workspace' => array( 'type' => 'FlxAiTaskWorkspaceInput', 'description' => __( 'The files it may touch.', 'flx-ai' ) ),
);

flx_gql_mutation(
	'flxAiTaskSave',
	array(
		'description' => __( 'Creates a task, or changes one when `id` is given. Fields you leave out keep their value.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array( 'id' => array( 'type' => 'Int', 'description' => __( 'Task to change; leave out to create one.', 'flx-ai' ) ) ) + $flxai_task_fields,
		'output'      => array( 'task' => array( 'type' => 'FlxAiTask', 'description' => __( 'The task after saving; null when the input was refused.', 'flx-ai' ) ) ),
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_int( $input['id'] ?? 0 );
			unset( $input['id'] );
			$r = FLXAI_Tasks_Service::save( $input, $id );
			return $r instanceof WP_Error ? $r : array( 'task' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiTaskRun',
	array(
		'description' => __( 'Starts a task in a few seconds; its report appears in the task chat.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Task.', 'flx-ai' ) ) ),
		'output'      => array( 'task' => array( 'type' => 'FlxAiTask', 'description' => __( 'The task.', 'flx-ai' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Tasks_Service::run( flx_gql_int( $input['id'] ?? 0 ) );
			return $r instanceof WP_Error ? $r : array( 'task' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiTaskSetActive',
	array(
		'description' => __( 'Pauses or resumes a task.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id'     => array( 'type' => 'Int!', 'description' => __( 'Task.', 'flx-ai' ) ),
			'active' => array( 'type' => 'Boolean!', 'description' => __( 'Resume (false pauses).', 'flx-ai' ) ),
		),
		'output'      => array( 'task' => array( 'type' => 'FlxAiTask', 'description' => __( 'The task.', 'flx-ai' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Tasks_Service::set_active( flx_gql_int( $input['id'] ?? 0 ), ! empty( $input['active'] ) );
			return $r instanceof WP_Error ? $r : array( 'task' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiTaskDelete',
	array(
		'description' => __( 'Deletes a task. Its chat is kept.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Task.', 'flx-ai' ) ) ),
		'output'      => array( 'deleted' => array( 'type' => 'Boolean', 'description' => __( 'The task is gone.', 'flx-ai' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Tasks_Service::delete( flx_gql_int( $input['id'] ?? 0 ) );
			return $r instanceof WP_Error ? $r : array( 'deleted' => true );
		},
	)
);

flx_gql_mutation(
	'flxAiTaskMarkRead',
	array(
		'description' => __( 'Marks the report of a task as read.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array( 'id' => array( 'type' => 'Int!', 'description' => __( 'Task.', 'flx-ai' ) ) ),
		'output'      => array( 'task' => array( 'type' => 'FlxAiTask', 'description' => __( 'The task.', 'flx-ai' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Tasks_Service::mark_read( flx_gql_int( $input['id'] ?? 0 ) );
			return $r instanceof WP_Error ? $r : array( 'task' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiTasksToggle',
	array(
		'description' => __( 'Turns the whole task system on or off.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array( 'enabled' => array( 'type' => 'Boolean!', 'description' => __( 'On (false turns all tasks off).', 'flx-ai' ) ) ),
		'output'      => array( 'overview' => array( 'type' => 'FlxAiTaskOverview', 'description' => __( 'The page after the change.', 'flx-ai' ) ) ),
		'resolve'     => static function ( array $input ) {
			return array( 'overview' => FLXAI_Tasks_Service::toggle( ! empty( $input['enabled'] ) ) );
		},
	)
);
