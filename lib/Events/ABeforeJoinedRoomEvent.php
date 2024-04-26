<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;

abstract class ABeforeJoinedRoomEvent extends ARoomEvent {
	protected bool $cancelJoin = false;

	public function __construct(
		Room $room,
		protected string $password,
		protected bool $passedPasswordProtection,
	) {
		parent::__construct($room);
	}

	public function setCancelJoin(bool $cancelJoin): void {
		$this->cancelJoin = $cancelJoin;
	}

	public function getCancelJoin(): bool {
		return $this->cancelJoin;
	}

	public function getPassword(): string {
		return $this->password;
	}

	public function setPassedPasswordProtection(bool $passedPasswordProtection): void {
		$this->passedPasswordProtection = $passedPasswordProtection;
	}

	public function getPassedPasswordProtection(): bool {
		return $this->passedPasswordProtection;
	}
}
