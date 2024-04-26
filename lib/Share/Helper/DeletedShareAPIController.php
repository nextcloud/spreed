<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Share\Helper;

use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCP\Share\IShare;

/**
 * Helper of OCA\Files_Sharing\Controller\DeletedShareAPIController for room
 * shares.
 *
 * The methods of this class are called from the DeletedShareAPIController to
 * perform actions or checks specific to room shares.
 */
class DeletedShareAPIController {

	public function __construct(
		private string $userId,
		private Manager $manager,
	) {
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
