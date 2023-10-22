<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

/**
 * @deprecated
 */
class SignalingRoomPropertiesEvent extends RoomEvent {

	public function __construct(
		Room $room,
		protected ?string $userId,
		protected array $properties,
	) {
		parent::__construct($room);
	}

	public function getUserId(): ?string {
		return $this->userId;
	}

	public function getProperties(): array {
		return $this->properties;
	}

	public function setProperty(string $property, $data): void {
		$this->properties[$property] = $data;
	}

	public function unsetProperty(string $property): void {
		unset($this->properties[$property]);
	}
}
