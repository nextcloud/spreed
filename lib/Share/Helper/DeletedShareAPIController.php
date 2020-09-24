<?php

declare(strict_types=1);
/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Talk\Share\Helper;

use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCP\Share\IShare;

/**
 * Helper of OCA\Files_Sharing\Controller\DeletedShareAPIController for room
 * shares.
 *
 * The methods of this class are called from the DeletedShareAPIController to
 * perform actions or checks specific to room shares.
 */
class DeletedShareAPIController {

	/** @var string */
	private $userId;
	/** @var Manager */
	private $manager;

	public function __construct(
			string $UserId,
			Manager $manager
	) {
		$this->userId = $UserId;
		$this->manager = $manager;
	}

	/**
	 * Formats the specific fields of a room share for OCS output.
	 *
	 * The returned fields override those set by the main
	 * DeletedShareAPIController.
	 *
	 * @param IShare $share
	 * @return array
	 */
	public function formatShare(IShare $share): array {
		$result = [];

		try {
			$room = $this->manager->getRoomByToken($share->getSharedWith(), $this->userId);
		} catch (RoomNotFoundException $e) {
			return $result;
		}

		$result['share_with_displayname'] = $room->getDisplayName($this->userId);

		return $result;
	}
}
