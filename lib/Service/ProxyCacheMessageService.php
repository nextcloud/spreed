<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
 *
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

namespace OCA\Talk\Service;

use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\ProxyCacheMessage;
use OCA\Talk\Model\ProxyCacheMessageMapper;
use OCA\Talk\Participant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception as DBException;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TalkChatMessageWithParent from ResponseDefinitions
 */
class ProxyCacheMessageService {
	public function __construct(
		protected ProxyCacheMessageMapper $mapper,
		protected LoggerInterface $logger,
		protected ITimeFactory $timeFactory,
	) {
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findByRemote(string $remoteServerUrl, string $remoteToken, int $remoteMessageId): ProxyCacheMessage {
		return $this->mapper->findByRemote($remoteServerUrl, $remoteToken, $remoteMessageId);
	}

	public function deleteExpiredMessages(): void {
		$this->mapper->deleteExpiredMessages($this->timeFactory->getDateTime());
	}

	/**
	 * @throws \InvalidArgumentException
	 * @throws CannotReachRemoteException
	 */
	public function syncRemoteMessage(Room $room, Participant $participant, int $messageId): ProxyCacheMessage {
		if (!$room->isFederatedConversation()) {
			throw new \InvalidArgumentException('room');
		}

		/** @var \OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController $proxy */
		$proxy = \OCP\Server::get(\OCA\Talk\Federation\Proxy\TalkV1\Controller\ChatController::class);
		$ocsResponse = $proxy->getMessageContext($room, $participant, $messageId, 1);

		if ($ocsResponse->getStatus() !== Http::STATUS_OK || !isset($ocsResponse->getData()[0])) {
			throw new \InvalidArgumentException('message');
		}

		/** @var TalkChatMessageWithParent $messageData */
		$messageData = $ocsResponse->getData()[0];

		$proxy = new ProxyCacheMessage();
		$proxy->setLocalToken($room->getToken());
		$proxy->setRemoteServerUrl($room->getRemoteServer());
		$proxy->setRemoteToken($room->getRemoteToken());
		$proxy->setRemoteMessageId($messageData['id']);
		$proxy->setActorType($messageData['actorType']);
		$proxy->setActorId($messageData['actorId']);
		$proxy->setActorDisplayName($messageData['actorDisplayName']);
		$proxy->setMessageType($messageData['messageType']);
		$proxy->setSystemMessage($messageData['systemMessage']);
		if ($messageData['expirationTimestamp']) {
			$proxy->setExpirationDatetime(new \DateTime('@' . $messageData['expirationTimestamp']));
		}
		$proxy->setCreationDatetime(new \DateTime('@' . $messageData['timestamp']));
		$proxy->setMessage($messageData['message']);
		$proxy->setMessageParameters(json_encode($messageData['messageParameters']));

		$metaData = [];
		if (!empty($messageData['lastEditActorType']) && !empty($messageData['lastEditActorId'])) {
			$metaData[Message::METADATA_LAST_EDITED_BY_TYPE] = $messageData['lastEditActorType'];
			$metaData[Message::METADATA_LAST_EDITED_BY_ID] = $messageData['lastEditActorId'];
		}
		if (!empty($messageData['lastEditTimestamp'])) {
			$metaData[Message::METADATA_LAST_EDITED_TIME] = $messageData['lastEditTimestamp'];
		}
		if (!empty($messageData['silent'])) {
			$metaData[Message::METADATA_SILENT] = $messageData['silent'];
		}
		$proxy->setMetaData(json_encode($metaData));

		try {
			$this->mapper->insert($proxy);
		} catch (DBException $e) {
			// DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION happens when
			// multiple users are in the same conversation. We are therefore
			// informed multiple times about the same remote message.
			if ($e->getReason() !== DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				$this->logger->error('Error saving proxy cache message failed: ' . $e->getMessage(), ['exception' => $e]);
				throw $e;
			}

			$proxy = $this->mapper->findByRemote(
				$room->getRemoteServer(),
				$room->getRemoteToken(),
				$messageData['id'],
			);
		}

		return $proxy;
	}
}
