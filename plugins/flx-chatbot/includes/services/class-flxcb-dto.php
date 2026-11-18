<?php
/**
 * Typed values the chat service returns: a conversation, a message, a product card, the figures,
 * a status row. The REST routes and the GraphQL schema read the same objects.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product card the assistant attached to a message.
 */
class FLXCB_Card {

	public function __construct(
		public readonly string $kind,
		public readonly string $title,
		public readonly string $url,
		public readonly string $image,
		public readonly string $price,
		public readonly string $stock,
		public readonly string $cart
	) {}

	/**
	 * @param mixed $row Card array from the message meta.
	 */
	public static function from_array( $row ): ?self {
		if ( ! is_array( $row ) ) {
			return null;
		}
		return new self(
			FLXCB_Val::str( $row['kind'] ?? 'product' ),
			FLXCB_Val::str( $row['title'] ?? '' ),
			FLXCB_Val::str( $row['url'] ?? '' ),
			FLXCB_Val::str( $row['image'] ?? '' ),
			FLXCB_Val::str( $row['price'] ?? '' ),
			FLXCB_Val::str( $row['stock'] ?? '' ),
			FLXCB_Val::str( $row['cart'] ?? '' )
		);
	}
}

/**
 * One chat message as an agent sees it.
 */
class FLXCB_Message {

	/**
	 * @param list<FLXCB_Card> $cards Product cards.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $role,
		public readonly string $type,
		public readonly string $body,
		public readonly int $ts,
		public readonly string $author,
		public readonly string $file_name,
		public readonly string $file_mime,
		public readonly string $file_url,
		public readonly array $cards
	) {}
}

/**
 * Last message line of the list.
 */
final class FLXCB_Last {

	public function __construct(
		public readonly string $role,
		public readonly string $body
	) {}
}

/**
 * One conversation as an agent sees it.
 */
class FLXCB_Conversation {

	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $name,
		public readonly string $email,
		public readonly string $phone,
		public readonly string $status,
		public readonly int $unread,
		public readonly int $rating,
		public readonly string $note,
		public readonly bool $lead,
		public readonly int $order_id,
		public readonly string $order_url,
		public readonly string $page_url,
		public readonly string $page_title,
		public readonly int $user_id,
		public readonly string $agent,
		public readonly string $flux_chat_url,
		public readonly string $summary,
		public readonly int $last_ts,
		public readonly bool $online,
		public readonly bool $typing,
		public readonly ?FLXCB_Last $last
	) {}
}

/**
 * Figures of the last N days.
 */
final class FLXCB_Stats {

	public function __construct(
		public readonly int $total,
		public readonly int $human,
		public readonly int $resolved,
		public readonly int $leads,
		public readonly int $orders,
		public readonly float $rating,
		public readonly int $rated,
		public readonly int $open
	) {}
}

/**
 * One line of the Connections card.
 */
final class FLXCB_Status_Row {

	public function __construct(
		public readonly string $key,
		public readonly string $state,
		public readonly string $title,
		public readonly string $text,
		public readonly string $url,
		public readonly string $url_label
	) {}
}

/**
 * Page of messages.
 */
final class FLXCB_Message_Page {

	/**
	 * @param list<FLXCB_Message> $nodes Messages, oldest first.
	 */
	public function __construct(
		public readonly array $nodes,
		public readonly int $last_id,
		public readonly bool $has_more
	) {}
}

/**
 * Scalar casts for values that come from WordPress options and database rows.
 */
final class FLXCB_Val {

	/**
	 * @param mixed $v Value.
	 */
	public static function str( $v, string $default = '' ): string {
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function int( $v, int $default = 0 ): int {
		return is_numeric( $v ) ? (int) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	public static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}
}
