<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Model;

/**
 * A room event (timeline or state) as delivered by /sync or /messages.
 */
final class Event {
	/**
	 * @param array<string, mixed> $content
	 * @param array<string, mixed> $unsigned
	 */
	public function __construct(
		public readonly string $eventId,
		public readonly string $type,
		public readonly string $sender,
		public readonly int $originServerTs,
		public readonly array $content,
		public readonly ?string $stateKey = null,
		public readonly array $unsigned = [],
		public readonly string $roomId = '',
	) {
	}

	/** @param array<string, mixed> $raw */
	public static function fromArray(array $raw, string $roomId = ''): self {
		return new self(
			(string)($raw['event_id'] ?? ''),
			(string)($raw['type'] ?? ''),
			(string)($raw['sender'] ?? ''),
			(int)($raw['origin_server_ts'] ?? 0),
			is_array($raw['content'] ?? null) ? $raw['content'] : [],
			array_key_exists('state_key', $raw) ? (string)$raw['state_key'] : null,
			is_array($raw['unsigned'] ?? null) ? $raw['unsigned'] : [],
			$roomId !== '' ? $roomId : (string)($raw['room_id'] ?? ''),
		);
	}

	public function isState(): bool {
		return $this->stateKey !== null;
	}

	public function isRedacted(): bool {
		return isset($this->unsigned['redacted_because']);
	}

	/** Own transaction id echoed back by the homeserver for events we sent from this device. */
	public function getTransactionId(): ?string {
		$txn = $this->unsigned['transaction_id'] ?? null;
		return is_string($txn) ? $txn : null;
	}

	/** @return array<string, mixed>|null */
	public function getRelation(): ?array {
		$rel = $this->content['m.relates_to'] ?? null;
		return is_array($rel) ? $rel : null;
	}

	public function getRelationType(): ?string {
		$rel = $this->getRelation();
		if ($rel === null) {
			return null;
		}
		if (isset($rel['m.in_reply_to']) && !isset($rel['rel_type'])) {
			return 'm.in_reply_to';
		}
		return isset($rel['rel_type']) ? (string)$rel['rel_type'] : null;
	}

	public function getRelatedEventId(): ?string {
		$rel = $this->getRelation();
		if ($rel === null) {
			return null;
		}
		if (isset($rel['event_id'])) {
			return (string)$rel['event_id'];
		}
		return isset($rel['m.in_reply_to']['event_id']) ? (string)$rel['m.in_reply_to']['event_id'] : null;
	}

	public function getInReplyTo(): ?string {
		$rel = $this->getRelation();
		return isset($rel['m.in_reply_to']['event_id']) ? (string)$rel['m.in_reply_to']['event_id'] : null;
	}

	public function getMsgType(): ?string {
		return isset($this->content['msgtype']) ? (string)$this->content['msgtype'] : null;
	}

	public function getBody(): string {
		return is_string($this->content['body'] ?? null) ? $this->content['body'] : '';
	}

	public function getFormattedBody(): ?string {
		if (($this->content['format'] ?? null) !== 'org.matrix.custom.html') {
			return null;
		}
		return is_string($this->content['formatted_body'] ?? null) ? $this->content['formatted_body'] : null;
	}

	/** @return array<string, mixed> */
	public function toArray(): array {
		$raw = [
			'event_id' => $this->eventId,
			'type' => $this->type,
			'sender' => $this->sender,
			'origin_server_ts' => $this->originServerTs,
			'content' => $this->content,
			'unsigned' => $this->unsigned,
		];
		if ($this->stateKey !== null) {
			$raw['state_key'] = $this->stateKey;
		}
		if ($this->roomId !== '') {
			$raw['room_id'] = $this->roomId;
		}
		return $raw;
	}
}
