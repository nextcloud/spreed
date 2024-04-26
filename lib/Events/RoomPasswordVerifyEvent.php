<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;

class RoomPasswordVerifyEvent extends ARoomEvent {
	protected ?bool $isPasswordValid = null;
	protected string $redirectUrl = '';


	public function __construct(
		Room $room,
		protected string $password,
	) {
		parent::__construct($room);
	}

	public function getPassword(): string {
		return $this->password;
	}

	public function setIsPasswordValid(bool $isPasswordValid): void {
		$this->isPasswordValid = $isPasswordValid;
	}

	public function isPasswordValid(): ?bool {
		return $this->isPasswordValid;
	}

	public function setRedirectUrl(string $redirectUrl): void {
		$this->redirectUrl = $redirectUrl;
	}

	public function getRedirectUrl(): string {
		return $this->redirectUrl;
	}
}
