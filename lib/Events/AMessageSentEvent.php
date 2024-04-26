<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\Comments\IComment;

abstract class AMessageSentEvent extends ARoomEvent {
	public function __construct(
		Room $room,
		protected IComment $comment,
		protected ?Participant $participant = null,
		protected bool $silent = false,
		protected ?IComment $parent = null,
	) {
		parent::__construct(
			$room,
		);
	}

	public function getComment(): IComment {
		return $this->comment;
	}

	public function getParticipant(): ?Participant {
		return $this->participant;
	}

	public function isSilentMessage(): bool {
		return $this->silent;
	}

	public function getParent(): ?IComment {
		return $this->parent;
	}
}
