<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Signaling;

use OCA\Talk\Model\Session;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class Messages {

	/** @var IDBConnection */
	protected $db;

	/** @var ParticipantService */
	protected $participantService;

	/** @var ITimeFactory */
	protected $timeFactory;

	public function __construct(IDBConnection $db,
								ParticipantService $participantService,
								ITimeFactory $timeFactory) {
		$this->db = $db;
		$this->participantService = $participantService;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @param string[] $sessionIds
	 */
	public function deleteMessages(array $sessionIds): void {
		$query = $this->db->getQueryBuilder();
		$query->delete('talk_internalsignaling')
			->where($query->expr()->in('recipient', $query->createNamedParameter($sessionIds, IQueryBuilder::PARAM_STR_ARRAY)))
			->orWhere($query->expr()->in('sender', $query->createNamedParameter($sessionIds, IQueryBuilder::PARAM_STR_ARRAY)));
		$query->execute();
	}

	/**
	 * @param string $senderSessionId
	 * @param string $recipientSessionId
	 * @param string $message
	 */
	public function addMessage(string $senderSessionId, string $recipientSessionId, string $message): void {
		$query = $this->db->getQueryBuilder();
		$query->insert('talk_internalsignaling')
			->values(
				[
					'sender' => $query->createNamedParameter($senderSessionId),
					'recipient' => $query->createNamedParameter($recipientSessionId),
					'timestamp' => $query->createNamedParameter($this->timeFactory->getTime()),
					'message' => $query->createNamedParameter($message),
				]
			);
		$query->execute();
	}

	/**
	 * @param Room $room
	 * @param string $message
	 */
	public function addMessageForAllParticipants(Room $room, string $message): void {
		$query = $this->db->getQueryBuilder();
		$query->insert('talk_internalsignaling')
			->values(
				[
					'sender' => $query->createParameter('sender'),
					'recipient' => $query->createParameter('recipient'),
					'timestamp' => $query->createNamedParameter($this->timeFactory->getTime()),
					'message' => $query->createNamedParameter($message),
				]
			);

		$participants = $this->participantService->getParticipantsForAllSessions($room);
		foreach ($participants as $participant) {
			$session = $participant->getSession();
			if ($session instanceof Session) {
				$query->setParameter('sender', $session->getSessionId())
					->setParameter('recipient', $session->getSessionId())
					->execute();
			}
		}
	}

	/**
	 * Get messages and delete them afterwards
	 *
	 * To make sure we don't delete messages which we didn't return
	 * we do it with 1 second difference. This means you don't receive messages
	 * immediately, but the next polling is only 1 second later and will get the
	 * "new" message.
	 *
	 * @param string $sessionId
	 * @return array
	 */
	public function getAndDeleteMessages(string $sessionId): array {
		$messages = [];
		$time = $this->timeFactory->getTime() - 1;

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_internalsignaling')
			->where($query->expr()->eq('recipient', $query->createNamedParameter($sessionId)))
			->andWhere($query->expr()->lte('timestamp', $query->createNamedParameter($time)));
		$result = $query->execute();

		while ($row = $result->fetch()) {
			$messages[] = ['type' => 'message', 'data' => $row['message']];
		}
		$result->closeCursor();

		$query = $this->db->getQueryBuilder();
		$query->delete('talk_internalsignaling')
			->where($query->expr()->eq('recipient', $query->createNamedParameter($sessionId)))
			->andWhere($query->expr()->lte('timestamp', $query->createNamedParameter($time)));
		$query->execute();

		return $messages;
	}

	/**
	 * Expires all signaling messages that are too old or invalid
	 *
	 * @param int $olderThan
	 */
	public function expireOlderThan(int $olderThan): void {
		$time = $this->timeFactory->getTime() - $olderThan;

		$query = $this->db->getQueryBuilder();
		$query->delete('talk_internalsignaling')
			->where($query->expr()->lt('timestamp', $query->createNamedParameter($time)));
		$query->execute();
	}
}
