<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Events;

use OCA\Talk\Room;

class RoomPasswordVerifyEvent extends RoomEvent {
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
