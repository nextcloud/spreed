<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Model;

/**
 * A room event (timeline or state)
 */
final class Event {
	/**
	 * @param array<string, mixed> $content
	 */
	public function __construct(
		public readonly string $eventId,
		public readonly string $type,
		public readonly string $sender,
		public readonly array $content,
		public readonly ?string $stateKey = null,
		public readonly int $originServerTs = 0,
		/** Event removed by a redaction event */
		public readonly ?string $redacts = null,
	) {
	}

	/** @param array<string, mixed> $raw */
	public static function fromArray(array $raw): self {
		return new self(
			(string)($raw['event_id'] ?? ''),
			(string)($raw['type'] ?? ''),
			(string)($raw['sender'] ?? ''),
			is_array($raw['content'] ?? null) ? $raw['content'] : [],
			array_key_exists('state_key', $raw) ? (string)$raw['state_key'] : null,
			(int)($raw['origin_server_ts'] ?? 0),
			// Since room version 11 the redacted event is in the content instead of the top level
			is_string($raw['redacts'] ?? null) ? $raw['redacts'] : (is_string($raw['content']['redacts'] ?? null) ? $raw['content']['redacts'] : null),
		);
	}

	public function isState(): bool {
		return $this->stateKey !== null;
	}

	/** @return array<string, mixed> */
	public function getRelation(): array {
		return is_array($this->content['m.relates_to'] ?? null) ? $this->content['m.relates_to'] : [];
	}

	public function getRelationType(): ?string {
		return is_string($this->getRelation()['rel_type'] ?? null) ? $this->getRelation()['rel_type'] : null;
	}

	public function getRelatedEventId(): ?string {
		return is_string($this->getRelation()['event_id'] ?? null) ? $this->getRelation()['event_id'] : null;
	}

	public function getInReplyTo(): ?string {
		$relation = $this->getRelation();
		return is_string($relation['m.in_reply_to']['event_id'] ?? null) ? $relation['m.in_reply_to']['event_id'] : null;
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
}
