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
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\File;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IPreview;
use OCP\IRequest;

class PreviewController extends AEnvironmentAwareController {

	/** @var ChatManager */
	protected $chatManager;
	/** @var IRootFolder */
	protected $rootFolder;
	/** @var ITimeFactory */
	protected $timeFactory;
	/** @var IConfig */
	protected $config;
	/** @var IPreview */
	protected $preview;

	public function __construct(
			string $appName,
			IRequest $request,
			ChatManager $chatManager,
			IRootFolder $rootFolder,
			ITimeFactory $timeFactory,
			IPreview $preview,
			IConfig $config
	) {
		parent::__construct($appName, $request);
		$this->chatManager = $chatManager;
		$this->rootFolder = $rootFolder;
		$this->timeFactory = $timeFactory;
		$this->preview = $preview;
		$this->config = $config;
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


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @RequireParticipant
	 * @RequireModeratorOrNoLobby
	 *
	 * @param int $fileId
	 * @param int $x
	 * @param int $y
	 * @param bool $a
	 * @param bool $forceIcon
	 * @param string $mode
	 *
	 * @return DataResponse|FileDisplayResponse
	 */
	public function getPreviewByFileId(
		int $fileId = -1,
		int $x = 32,
		int $y = 32,
		bool $a = false,
		bool $forceIcon = true,
		string $mode = 'fill') {

		if ($fileId === -1 || $x === 0 || $y === 0) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$conversationFolder = $this->getConversationFolder($this->getRoom());
		$nodes = $conversationFolder->getById($fileId);
		if (empty($nodes)) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$node = array_pop($nodes);

		return $this->fetchPreview($node, $x, $y, $a, $forceIcon, $mode);
	}

	/**
	 * @param Node $node
	 * @param int $x
	 * @param int $y
	 * @param bool $a
	 * @param bool $forceIcon
	 * @param string $mode
	 * @return DataResponse|FileDisplayResponse
	 */
	private function fetchPreview(
		Node $node,
		int $x,
		int $y,
		bool $a,
		bool $forceIcon ,
		string $mode) : Http\Response {

		if (!($node instanceof File) || (!$forceIcon && !$this->preview->isAvailable($node))) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		if (!$node->isReadable()) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$f = $this->preview->getPreview($node, $x, $y, !$a, $mode);
			$response = new FileDisplayResponse($f, Http::STATUS_OK, ['Content-Type' => $f->getMimeType()]);
//			$response->cacheFor(3600 * 24);
			return $response;
		} catch (NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

	}
}
