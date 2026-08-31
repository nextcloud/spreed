<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Nextcloud\Matrix\Model;

/** One entry of `rooms.invite` – only stripped state is available. */
final class InvitedRoom {
	/** @param list<Event> $inviteState */
	public function __construct(
		public readonly string $roomId,
		public readonly array $inviteState,
	) {
	}

	/** @param array<string, mixed> $raw */
	public static function fromArray(string $roomId, array $raw): self {
		$events = is_array($raw['invite_state']['events'] ?? null) ? $raw['invite_state']['events'] : [];
		return new self($roomId, array_map(static fn (array $e) => Event::fromArray($e, $roomId), array_values(array_filter($events, 'is_array'))));
	}

	public function getState(): RoomState {
		$state = new RoomState($this->roomId);
		$state->applyAll($this->inviteState);
		return $state;
	}

	/** The membership event that invited $userId, if present in the stripped state. */
	public function getInviteEvent(string $userId): ?Event {
		foreach ($this->inviteState as $event) {
			if ($event->type === 'm.room.member' && $event->stateKey === $userId && ($event->content['membership'] ?? '') === Member::INVITE) {
				return $event;
			}
		}
		return null;
	}

	public function getInviter(string $userId): string {
		return $this->getInviteEvent($userId)?->sender ?? '';
	}

	public function isDirect(string $userId): bool {
		return (bool)($this->getInviteEvent($userId)?->content['is_direct'] ?? false);
	}
}
