<?php
/**
 * Value objects of the scheduled tasks.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** When a task repeats. */
final class FLXAI_Task_Schedule {

	/** @param list<int> $days Weekdays 1 (Monday) – 7 (Sunday). */
	public function __construct(
		public readonly string $freq,
		public readonly string $time,
		public readonly array $days,
		public readonly int $day,
		public readonly int $every,
		public readonly string $at,
	) {}
}

/** A person, address, number or group that is told about a run. */
final class FLXAI_Task_Recipient {

	/** @param list<string> $channels email, sms, whatsapp. */
	public function __construct(
		public readonly string $kind,
		public readonly int $id,
		public readonly string $name,
		public readonly string $email,
		public readonly string $phone,
		public readonly string $jid,
		public readonly string $sub,
		public readonly array $channels,
	) {}
}

/** How a task tells the owner (and others) about its runs. */
final class FLXAI_Task_Notify {

	/** @param list<FLXAI_Task_Recipient> $to More recipients. */
	public function __construct(
		public readonly string $mode,
		public readonly bool $email,
		public readonly bool $panel,
		public readonly string $whatsapp,
		public readonly bool $promo,
		public readonly array $to,
		public readonly string $label,
	) {}
}

/** The files a task may touch. */
final class FLXAI_Task_Workspace {

	/** @param list<string> $folders Allowed folders. */
	public function __construct(
		public readonly string $dir,
		public readonly string $access,
		public readonly array $folders,
	) {}
}

/** A scheduled task. */
final class FLXAI_Task {

	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $prompt,
		public readonly FLXAI_Task_Schedule $schedule,
		public readonly string $schedule_label,
		public readonly string $status,
		public readonly ?int $next_run,
		public readonly ?int $last_run,
		public readonly string $last_state,
		public readonly string $last_error,
		public readonly string $last_output,
		public readonly int $runs,
		public readonly float $cost,
		public readonly int $avg_ms,
		public readonly int $chat_id,
		public readonly string $owner,
		public readonly bool $mine,
		public readonly string $skill,
		public readonly bool $unread,
		public readonly FLXAI_Task_Notify $notify,
		public readonly ?FLXAI_Task_Workspace $workspace,
	) {}
}

/** A skill a task can run. */
final class FLXAI_Task_Skill {

	public function __construct(
		public readonly string $slug,
		public readonly string $name,
		public readonly bool $active,
	) {}
}

/** Which channels can carry a notification. */
final class FLXAI_Task_Channels {

	public function __construct(
		public readonly bool $email,
		public readonly bool $sms,
		public readonly bool $whatsapp,
	) {}
}

/** The tasks page. */
final class FLXAI_Task_Overview {

	/**
	 * @param list<FLXAI_Task>       $tasks  Tasks.
	 * @param list<FLXAI_Task_Skill> $skills Skills a task can use.
	 */
	public function __construct(
		public readonly array $tasks,
		public readonly bool $enabled,
		public readonly ?int $next_run,
		public readonly int $awaiting,
		public readonly ?int $last_check,
		public readonly bool $workspace_ready,
		public readonly bool $whatsapp_ready,
		public readonly string $owner_email,
		public readonly string $timezone,
		public readonly array $skills,
		public readonly FLXAI_Task_Channels $channels,
	) {}
}

/** Recipient search result. */
final class FLXAI_Task_Search {

	/** @param list<FLXAI_Task_Recipient> $items Matches. */
	public function __construct(
		public readonly array $items,
		public readonly FLXAI_Task_Channels $channels,
	) {}
}
