<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Participant;
use OCA\Talk\Room;

class BeforeSignalingResponseSentEvent extends ARoomEvent {
	protected array $session = [];

	public function __construct(
		Room $room,
		protected Participant $participant,
		protected string $action,
	) {
		parent::__construct($room);
	}

	public function getParticipant(): Participant {
		return $this->participant;
	}

	public function getAction(): string {
		return $this->action;
	}

	public function setSession(array $session): void {
		$this->session = $session;
	}

	public function getSession(): array {
		return $this->session;
	}
}
