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
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class Messages {
	use TTransactional;

	public function __construct(
		protected IDBConnection $db,
		protected ParticipantService $participantService,
		protected ITimeFactory $timeFactory,
	) {
	}

	/**
	 * @param string[] $sessionIds
	 */
	public function deleteMessages(array $sessionIds): void {
		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_internalsignaling')
			->where($delete->expr()->in('recipient', $delete->createNamedParameter($sessionIds, IQueryBuilder::PARAM_STR_ARRAY)))
			->orWhere($delete->expr()->in('sender', $delete->createNamedParameter($sessionIds, IQueryBuilder::PARAM_STR_ARRAY)));

		$this->atomic(function () use ($delete) {
			$delete->executeStatement();
		}, $this->db);
	}

	/**
	 * @param string $senderSessionId
	 * @param string $recipientSessionId
	 * @param string $message
	 */
	public function addMessage(string $senderSessionId, string $recipientSessionId, string $message): void {
		$insert = $this->db->getQueryBuilder();
		$insert->insert('talk_internalsignaling')
			->values(
				[
					'sender' => $insert->createNamedParameter($senderSessionId),
					'recipient' => $insert->createNamedParameter($recipientSessionId),
					'timestamp' => $insert->createNamedParameter($this->timeFactory->getTime()),
					'message' => $insert->createNamedParameter($message),
				]
			);
		$insert->executeStatement();
	}

	/**
	 * @param Room $room
	 * @param string $message
	 */
	public function addMessageForAllParticipants(Room $room, string $message): void {
		$insert = $this->db->getQueryBuilder();
		$insert->insert('talk_internalsignaling')
			->values(
				[
					'sender' => $insert->createParameter('sender'),
					'recipient' => $insert->createParameter('recipient'),
					'timestamp' => $insert->createNamedParameter($this->timeFactory->getTime()),
					'message' => $insert->createNamedParameter($message),
				]
			);

		$participants = $this->participantService->getParticipantsForAllSessions($room);
		$this->atomic(function () use ($participants, $insert) {
			foreach ($participants as $participant) {
				$session = $participant->getSession();
				if ($session instanceof Session) {
					$insert->setParameter('sender', $session->getSessionId())
						->setParameter('recipient', $session->getSessionId())
						->executeStatement();
				}
			}
		}, $this->db);
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
	 * @return list<array{type: string, data: string}>
	 */
	public function getAndDeleteMessages(string $sessionId): array {
		$messages = [];
		$time = $this->timeFactory->getTime() - 1;

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('talk_internalsignaling')
			->where($query->expr()->eq('recipient', $query->createNamedParameter($sessionId)))
			->andWhere($query->expr()->lte('timestamp', $query->createNamedParameter($time)))
			->orderBy('id', 'ASC');

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_internalsignaling')
			->where($delete->expr()->eq('recipient', $delete->createNamedParameter($sessionId)))
			->andWhere($delete->expr()->lte('timestamp', $delete->createNamedParameter($time)));

		$this->atomic(function () use (&$messages, $query, $delete) {
			$result = $query->executeQuery();

			while ($row = $result->fetch()) {
				$messages[] = ['type' => 'message', 'data' => $row['message']];
			}
			$result->closeCursor();

			$delete->executeStatement();
		}, $this->db);

		return $messages;
	}

	/**
	 * Expires all signaling messages that are too old or invalid
	 *
	 * @param int $olderThan
	 */
	public function expireOlderThan(int $olderThan): void {
		$time = $this->timeFactory->getTime() - $olderThan;

		$delete = $this->db->getQueryBuilder();
		$delete->delete('talk_internalsignaling')
			->where($delete->expr()->lt('timestamp', $delete->createNamedParameter($time)));

		$this->atomic(function () use ($delete) {
			$delete->executeStatement();
		}, $this->db);
	}
}
