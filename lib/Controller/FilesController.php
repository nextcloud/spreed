<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019, Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Controller;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\File;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IRequest;

class FilesController extends AEnvironmentAwareController {

	/** @var ChatManager */
	protected $chatManager;
	/** @var IRootFolder */
	protected $rootFolder;
	/** @var ITimeFactory */
	protected $timeFactory;
	/** @var IConfig */
	protected $config;

	public function __construct(
			string $appName,
			IRequest $request,
			ChatManager $chatManager,
			IRootFolder $rootFolder,
			ITimeFactory $timeFactory,
			IConfig $config
	) {
		parent::__construct($appName, $request);
		$this->chatManager = $chatManager;
		$this->rootFolder = $rootFolder;
		$this->timeFactory = $timeFactory;
		$this->config = $config;
	}

	/**
	 * @NoAdminRequired
	 * @RequireParticipant
	 * @RequireReadWriteConversation
	 * @RequireModeratorOrNoLobby
	 *
	 * @param string $path
	 * @return DataResponse
	 */
	public function copyFileToRoom(string $path): DataResponse {
		$participant = $this->getParticipant();
		$room = $this->getRoom();

		if (!$room instanceof Room) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!$participant instanceof Participant || $participant->isGuest()) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$userFolder = $this->rootFolder->getUserFolder($participant->getUser());
		try {
			/** @var File $node */
			$node = $userFolder->get($path);
		} catch (NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!$node instanceof File) {
			return new DataResponse([], Http::STATUS_METHOD_NOT_ALLOWED);
		}

		$folder = $this->getConversationFolder($room);

		$newFileName = $this->timeFactory->getTime() . '-' . $node->getName();
		try {
			$folder->get($newFileName);
			return new DataResponse([], Http::STATUS_CONFLICT);
		} catch (NotFoundException $e) {
			$file = $folder->newFile($newFileName);
		}

		$resource = $node->fopen('r');
		$file->putContent($resource);
		fclose($resource);

		$message = ['message' => 'attachment', 'parameters' => ['fileId' => $file->getId()]];

		$this->chatManager->addSystemMessage(
			$room, 'users', $participant->getUser(),
			json_encode($message), $this->timeFactory->getDateTime(), true
		);

		return new DataResponse([], Http::STATUS_CREATED);
	}

	protected function getConversationFolder(Room $room): Folder {
		$instanceId = $this->config->getSystemValueString('instanceid');
		$appDataFolderName = 'appdata_' . $instanceId . '/talk'; // FIXME appId

		try {
			/** @var Folder $appDataFolder */
			$appDataFolder = $this->rootFolder->get($appDataFolderName);
		} catch (NotFoundException $e) {
			/** @var Folder $appDataFolder */
			$appDataFolder = $this->rootFolder->newFolder($appDataFolderName);
		}

		try {
			$folder = $appDataFolder->get($room->getToken());
		} catch (NotFoundException $e) {
			$folder = $appDataFolder->newFolder($room->getToken());
		}

		return $folder;
	}
}
