<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Joas Schilling <coding@schilljs.com>
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

use OCA\Spreed\Manager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Share\IManager as IShareManager;
use OC\User\NoUserException;

class FileSharingController extends OCSController {
	/** @var string */
	private $userId;
	/** @var Folder */
	private $userFolder;
	/** @var IShareManager */
	private $shareManager;

	/**
	 * @param string $appName
	 * @param string $userId
	 * @param IRequest $request
	 * @param IRootFolder $rootFolder
	 * @param IShareManager $shareManager
	 */
	public function __construct($appName, $userId, IRequest $request, IRootFolder $rootFolder, IShareManager $shareManager) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		try {
			$this->userFolder = $rootFolder->getUserFolder($userId);
		} catch (\Exception $e) { /* Silently ignore error */}
		$this->shareManager = $shareManager;
	}

	private function getUserFolder() {
		if ($this->userFolder) {
			return $this->userFolder;
		}
		throw new NoUserException('no user');
	}

	/**
	 * Share a file and return the share's token
	 *
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function shareAtPath($path) {
		try {
			$file = $this->getUserFolder()->get($path);
		} catch (NoUserException $e) {
			// This will never happen, this is not a public page -> user account is required
			return new DataResponse(array('error' => $e->getMessage()));
		} catch (NotFoundException $e) {
			return new DataResponse(array('error' => 'File not found'));
		}
		$userId = $this->userId;
		$manager = $this->shareManager;
		$shareType = \OCP\Share::SHARE_TYPE_LINK;
		$permissions = \OCP\Constants::PERMISSION_READ;

		$existingShares = $manager->getSharesBy($userId, $shareType, $file, false, 1);
		// TODO(leon): Possible data race here
		if (count($existingShares) > 0) {
			// We already have our share
			$share = $existingShares[0];
		} else {
			// Not shared yet, share it now
			$share = $manager->newShare();
			$share
				->setNode($file)
				->setShareType($shareType)
				->setPermissions($permissions)
				->setSharedBy($userId);
			$manager->createShare($share);
		}

		return new DataResponse(array('token' => $share->getToken()));
	}
}
