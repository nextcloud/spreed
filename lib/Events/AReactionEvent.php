<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;
use OCP\Comments\IComment;

abstract class AReactionEvent extends ARoomEvent {
	public function __construct(
		Room $room,
		protected IComment $message,
		protected string $actorType,
		protected string $actorId,
		protected string $actorDisplayName,
		protected string $reaction,
	) {
		parent::__construct($room);
	}

	public function getMessage(): IComment {
		return $this->message;
	}

	public function getActorType(): string {
		return $this->actorType;
	}

	public function getActorId(): string {
		return $this->actorId;
	}

	public function getActorDisplayName(): string {
		return $this->actorDisplayName;
	}

	public function getReaction(): string {
		return $this->reaction;
	}
}
