<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Client\Model;

/**
 * Current state of a room, built by applying state events in order
 */
final class RoomState {
	public const TYPE_SPACE = 'm.space';

	public ?string $name = null;
	public ?string $topic = null;
	public ?string $canonicalAlias = null;
	public ?string $roomType = null;
	public string $creator = '';
	public bool $encrypted = false;
	/** @var array<string, mixed> */
	public array $powerLevels = [];
	/** @var array<string, Member> */
	private array $members = [];

	public function __construct(
		public readonly string $roomId,
	) {
	}

	/** @param iterable<Event> $events */
	public function applyAll(iterable $events): void {
		foreach ($events as $event) {
			$this->apply($event);
		}
	}

	public function apply(Event $event): void {
		if (!$event->isState()) {
			return;
		}

		$content = $event->content;
		switch ($event->type) {
			case 'm.room.create':
				$this->roomType = is_string($content['type'] ?? null) ? $content['type'] : null;
				$this->creator = is_string($content['creator'] ?? null) ? $content['creator'] : $event->sender;
				break;
			case 'm.room.encryption':
				$this->encrypted = true;
				break;
			case 'm.room.power_levels':
				$this->powerLevels = $content;
				break;
			case 'm.room.name':
				$name = is_string($content['name'] ?? null) ? trim($content['name']) : '';
				$this->name = $name !== '' ? $name : null;
				break;
			case 'm.room.topic':
				$topic = is_string($content['topic'] ?? null) ? trim($content['topic']) : '';
				$this->topic = $topic !== '' ? $topic : null;
				break;
			case 'm.room.canonical_alias':
				$this->canonicalAlias = is_string($content['alias'] ?? null) && $content['alias'] !== '' ? $content['alias'] : null;
				break;
			case 'm.room.member':
				$member = Member::fromEvent($event);
				if ($member->membership === Member::LEAVE && !isset($this->members[$member->userId])) {
					break;
				}
				$this->members[$member->userId] = $member;
				break;
		}
	}

	public function getPowerLevels(): PowerLevels {
		return new PowerLevels($this->powerLevels, $this->creator);
	}

	public function isSpace(): bool {
		return $this->roomType === self::TYPE_SPACE;
	}

	/** @return array<string, Member> */
	public function getMembers(): array {
		return $this->members;
	}
}
