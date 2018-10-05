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

use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Files\Util;
use OCA\Spreed\Manager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\IRequest;

class FilesController extends OCSController {

	/** @var string */
	private $currentUser;
	/** @var Manager */
	private $manager;
	/** @var Util */
	private $util;
	/** @var IL10N */
	private $l;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param string $userId
	 * @param Manager $manager
	 * @param Util $util
	 * @param IL10N $l10n
	 */
	public function __construct(
			string $appName,
			IRequest $request,
			string $userId,
			Manager $manager,
			Util $util,
			IL10N $l10n
	) {
		parent::__construct($appName, $request);
		$this->currentUser = $userId;
		$this->manager = $manager;
		$this->util = $util;
		$this->l = $l10n;
	}

	/**
	 * @NoAdminRequired
	 *
	 * Returns the token of the room associated to the given file id.
	 *
	 * If there is no room associated to the given file id a new room is
	 * created; the new room is a public room associated with a "file" object
	 * with the given file id. Unlike normal rooms in which the owner is the
	 * user that created the room these are special rooms without owner or any
	 * other persistent participant.
	 *
	 * In any case, to create or even get the token of the room, the file must
	 * be shared and the user must have direct access to that file; an error
	 * is returned otherwise. A user has direct access to a file if she has
	 * access to it through a user, group, circle or room share (but not through
	 * a link share, for example), or if she is the owner of such a file.
	 *
	 * @param string $fileId
	 * @return DataResponse the status code is "200 OK" if a room is returned,
	 *         or "404 Not found" if the given file id was invalid.
	 */
	public function getRoom(string $fileId): DataResponse {
		$share = $this->util->getAnyDirectShareOfFileAccessibleByUser($fileId, $this->currentUser);
		if (!$share) {
			throw new OCSNotFoundException($this->l->t('File is not shared, or shared but not with the user'));
		}

		try {
			$room = $this->manager->getRoomByObject('file', $fileId);
		} catch (RoomNotFoundException $e) {
			$room = $this->manager->createPublicRoom('', 'file', $fileId);
		}

		return new DataResponse([
			'token' => $room->getToken()
		]);
	}

}
