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

namespace OCA\Spreed\Controller;

use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Files\Util;
use OCA\Spreed\Manager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\Files\FileInfo;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Share\IShare;

class FilesController extends OCSController {

	/** @var string */
	private $currentUser;
	/** @var Manager */
	private $manager;
	/** @var Util */
	private $util;
	/** @var IConfig */
	private $config;
	/** @var IL10N */
	private $l;

	public function __construct(
			string $appName,
			IRequest $request,
			string $userId,
			Manager $manager,
			Util $util,
			IConfig $config,
			IL10N $l10n
	) {
		parent::__construct($appName, $request);
		$this->currentUser = $userId;
		$this->manager = $manager;
		$this->util = $util;
		$this->config = $config;
		$this->l = $l10n;
	}

	/**
	 * @NoAdminRequired
	 *
	 * Returns the token of the room associated to the given file id.
	 *
	 * This is the counterpart of PublicShareController::getRoom() for file ids
	 * instead of share tokens, although both return the same room token if the
	 * given file id and share token refer to the same file.
	 *
	 * If there is no room associated to the given file id a new room is
	 * created; the new room is a public room associated with a "file" object
	 * with the given file id. Unlike normal rooms in which the owner is the
	 * user that created the room these are special rooms without owner
	 * (although self joined users with direct access to the file become
	 * persistent participants automatically when they join until they
	 * explicitly leave or no longer have access to the file).
	 *
	 * In any case, to create or even get the token of the room, the file must
	 * be shared and the user must be the owner of a public share of the file
	 * (like a link share, for example) or have direct access to that file; an
	 * error is returned otherwise. A user has direct access to a file if she
	 * has access to it (or to an ancestor) through a user, group, circle or
	 * room share (but not through a link share, for example), or if she is the
	 * owner of such a file.
	 *
	 * @param string $fileId
	 * @return DataResponse the status code is "200 OK" if a room is returned,
	 *         or "404 Not found" if the given file id was invalid.
	 * @throws OCSNotFoundException
	 */
	public function getRoom(string $fileId): DataResponse {
		if ($this->config->getAppValue('spreed', 'conversations_files', '1') !== '1') {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$share = $this->util->getAnyPublicShareOfFileOwnedByUserOrAnyDirectShareOfFileAccessibleByUser($fileId, $this->currentUser);
		$groupFolder = null;
		if (!$share) {
			$groupFolder = $this->util->getGroupFolderNode($fileId, $this->currentUser);
			if (!$groupFolder) {
				throw new OCSNotFoundException($this->l->t('File is not shared, or shared but not with the user'));
			}
		}

		try {
			$room = $this->manager->getRoomByObject('file', $fileId);
		} catch (RoomNotFoundException $e) {
			if ($share) {
				try {
					$name = $this->getFileName($share, $fileId);
				} catch (NotFoundException $e) {
					throw new OCSNotFoundException($this->l->t('File is not shared, or shared but not with the user'));
				}
			} else {
				$name = $groupFolder->getName();
			}
			$room = $this->manager->createPublicRoom($name, 'file', $fileId);
		}

		return new DataResponse([
			'token' => $room->getToken()
		]);
	}

	/**
	 * Returns the name of the file in the share.
	 *
	 * If the given share itself is a file its name is returned; otherwise the
	 * file is looked for in the given shared folder and its name is returned.
	 *
	 * @param IShare $share
	 * @param string $fileId
	 * @return string
	 * @throws NotFoundException
	 */
	private function getFileName(IShare $share, string $fileId): string {
		$node = $share->getNode();

		if ($node->getType() === FileInfo::TYPE_FILE) {
			return $node->getName();
		}

		$fileById = $node->getById($fileId);

		if (empty($fileById)) {
			throw new NotFoundException('File not found in share');
		}

		$file = array_shift($fileById);
		return $file->getName();
	}

}
