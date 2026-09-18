<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Model;

/**
 * Aggregated current state of a room, built by applying state events in order.
 */
final class RoomState {
	public const TYPE_SPACE = 'm.space';

	public ?string $name = null;
	public ?string $topic = null;
	public ?string $avatarUrl = null;
	public ?string $canonicalAlias = null;
	/** @var list<string> */
	public array $altAliases = [];
	public string $joinRule = 'invite';
	public string $historyVisibility = 'shared';
	public bool $guestAccess = false;
	public string $creator = '';
	public string $roomVersion = '1';
	public ?string $roomType = null;
	public ?string $encryptionAlgorithm = null;
	public ?int $rotationPeriodMs = null;
	public ?int $rotationPeriodMsgs = null;
	public ?string $tombstoneReplacement = null;
	public ?string $predecessorRoomId = null;
	/** @var array<string, mixed> */
	private array $powerLevelsContent = [];
	private bool $hasPowerLevels = false;
	/** @var array<string, Member> */
	private array $members = [];
	/** @var array<string, array<string, Event>> type → state_key → event, for everything not interpreted above */
	private array $other = [];

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
		$c = $event->content;
		switch ($event->type) {
			case 'm.room.create':
				$this->creator = (string)($c['creator'] ?? $event->sender);
				$this->roomVersion = (string)($c['room_version'] ?? '1');
				$this->roomType = is_string($c['type'] ?? null) ? $c['type'] : null;
				$this->predecessorRoomId = is_string($c['predecessor']['room_id'] ?? null) ? $c['predecessor']['room_id'] : null;
				break;
			case 'm.room.name':
				$name = trim((string)($c['name'] ?? ''));
				$this->name = $name === '' ? null : $name;
				break;
			case 'm.room.topic':
				$topic = (string)($c['topic'] ?? '');
				$this->topic = $topic === '' ? null : $topic;
				break;
			case 'm.room.avatar':
				$this->avatarUrl = is_string($c['url'] ?? null) && $c['url'] !== '' ? $c['url'] : null;
				break;
			case 'm.room.canonical_alias':
				$this->canonicalAlias = is_string($c['alias'] ?? null) && $c['alias'] !== '' ? $c['alias'] : null;
				$this->altAliases = array_values(array_filter(is_array($c['alt_aliases'] ?? null) ? $c['alt_aliases'] : [], 'is_string'));
				break;
			case 'm.room.join_rules':
				$this->joinRule = (string)($c['join_rule'] ?? 'invite');
				break;
			case 'm.room.history_visibility':
				$this->historyVisibility = (string)($c['history_visibility'] ?? 'shared');
				break;
			case 'm.room.guest_access':
				$this->guestAccess = ($c['guest_access'] ?? 'forbidden') === 'can_join';
				break;
			case 'm.room.encryption':
				$this->encryptionAlgorithm = (string)($c['algorithm'] ?? 'm.megolm.v1.aes-sha2');
				$this->rotationPeriodMs = isset($c['rotation_period_ms']) ? (int)$c['rotation_period_ms'] : null;
				$this->rotationPeriodMsgs = isset($c['rotation_period_msgs']) ? (int)$c['rotation_period_msgs'] : null;
				break;
			case 'm.room.tombstone':
				$this->tombstoneReplacement = is_string($c['replacement_room'] ?? null) ? $c['replacement_room'] : null;
				break;
			case 'm.room.power_levels':
				$this->powerLevelsContent = $c;
				$this->hasPowerLevels = true;
				break;
			case 'm.room.member':
				$member = Member::fromEvent($event);
				if ($member->membership === Member::LEAVE && !isset($this->members[$member->userId])) {
					// Never seen and already gone – nothing to track
					break;
				}
				$this->members[$member->userId] = $member;
				break;
			default:
				$this->other[$event->type][(string)$event->stateKey] = $event;
		}
	}

	public function getPowerLevels(): PowerLevels {
		return new PowerLevels($this->hasPowerLevels ? $this->powerLevelsContent : [], $this->creator);
	}

	public function hasPowerLevels(): bool {
		return $this->hasPowerLevels;
	}

	public function isEncrypted(): bool {
		return $this->encryptionAlgorithm !== null;
	}

	public function isSpace(): bool {
		return $this->roomType === self::TYPE_SPACE;
	}

	public function isUpgraded(): bool {
		return $this->tombstoneReplacement !== null;
	}

	/** @return array<string, Member> */
	public function getMembers(): array {
		return $this->members;
	}

	/** @return array<string, Member> */
	public function getJoinedMembers(): array {
		return array_filter($this->members, static fn (Member $m) => $m->isJoined());
	}

	/** @return array<string, Member> */
	public function getInvitedMembers(): array {
		return array_filter($this->members, static fn (Member $m) => $m->isInvited());
	}

	public function getMember(string $userId): ?Member {
		return $this->members[$userId] ?? null;
	}

	public function getMembership(string $userId): string {
		return $this->members[$userId]->membership ?? Member::LEAVE;
	}

	public function getOtherState(string $type, string $stateKey = ''): ?Event {
		return $this->other[$type][$stateKey] ?? null;
	}
}
