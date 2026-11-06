<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use OCA\Talk\Config;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\Poll;
use OCA\Talk\SetupCheck\ExpensiveRepairSteps;
use OCP\AppFramework\Services\IAppConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStepExpensive;

/**
 * Remove attachments and polls of chat messages that expired before the
 * expire date was stored on attachments and polls, and copy the expire date
 * of the remaining messages onto them.
 */
class CleanupExpiredMessageLeftovers implements IRepairStepExpensive {
	private const BATCH_SIZE = 1000;

	public function __construct(
		private readonly IDBConnection $connection,
		private readonly IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Clean up attachments and polls of expiring chat messages';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$this->deleteAttachmentsOfExpiredMessages($output);
		$this->copyExpireDateFromMessages();
		$this->appConfig->setAppValueInt(Config::EXPENSIVE_REPAIR_PERFORMED, ExpensiveRepairSteps::VERSION);
	}

	private function deleteAttachmentsOfExpiredMessages(IOutput $output): void {
		$select = $this->connection->getQueryBuilder();
		$select->select('a.id', 'a.room_id', 'a.object_type')
			->from('talk_attachments', 'a')
			->leftJoin('a', 'comments', 'c', $select->expr()->eq('a.message_id', 'c.id'))
			->where($select->expr()->isNull('c.id'))
			->andWhere($select->expr()->gt('a.id', $select->createParameter('offset')))
			->orderBy('a.id', 'ASC')
			->setMaxResults(self::BATCH_SIZE);

		$delete = $this->connection->getQueryBuilder();
		$delete->delete('talk_attachments')
			->where($delete->expr()->in('id', $delete->createParameter('ids')));

		$roomsWithExpiredPolls = [];
		$deletedAttachments = 0;
		$offset = 0;
		do {
			$select->setParameter('offset', $offset, IQueryBuilder::PARAM_INT);
			$result = $select->executeQuery();
			$rows = $result->fetchAllAssociative();
			$result->closeCursor();

			$ids = [];
			foreach ($rows as $row) {
				$offset = (int)$row['id'];
				$ids[] = $offset;
				if ($row['object_type'] === Attachment::TYPE_POLL) {
					$roomsWithExpiredPolls[(int)$row['room_id']] = true;
				}
			}

			if (!empty($ids)) {
				$delete->setParameter('ids', $ids, IQueryBuilder::PARAM_INT_ARRAY)
					->executeStatement();
				$deletedAttachments += count($ids);
			}
		} while (count($rows) === self::BATCH_SIZE);

		$deletedPolls = 0;
		foreach (array_keys($roomsWithExpiredPolls) as $roomId) {
			$deletedPolls += $this->deletePollsWithoutMessage($roomId);
		}

		if ($deletedAttachments || $deletedPolls) {
			$output->info('Removed ' . $deletedAttachments . ' attachments and ' . $deletedPolls . ' polls of expired messages');
		}
	}

	/**
	 * The poll id is only stored in the chat message, so a poll whose
	 * message expired is detected by no longer being referenced by any
	 * remaining poll message of the room.
	 */
	private function deletePollsWithoutMessage(int $roomId): int {
		$query = $this->connection->getQueryBuilder();
		$query->select('c.message')
			->from('talk_attachments', 'a')
			->innerJoin('a', 'comments', 'c', $query->expr()->eq('a.message_id', 'c.id'))
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('a.object_type', $query->createNamedParameter(Attachment::TYPE_POLL)));

		$referencedPollIds = [];
		$result = $query->executeQuery();
		while ($row = $result->fetchAssociative()) {
			$message = json_decode((string)$row['message'], true);
			$referencedPollIds[] = (int)($message['parameters']['objectId'] ?? 0);
		}
		$result->closeCursor();

		$query = $this->connection->getQueryBuilder();
		$query->select('id')
			->from('talk_polls')
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->neq('status', $query->createNamedParameter(Poll::STATUS_DRAFT, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$pollIds = array_map('intval', $result->fetchFirstColumn());
		$result->closeCursor();

		$expiredPollIds = array_values(array_diff($pollIds, $referencedPollIds));
		foreach (array_chunk($expiredPollIds, IQueryBuilder::MAX_IN_PARAMETERS) as $ids) {
			$delete = $this->connection->getQueryBuilder();
			$delete->delete('talk_poll_votes')
				->where($delete->expr()->in('poll_id', $delete->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
			$delete->executeStatement();

			$delete = $this->connection->getQueryBuilder();
			$delete->delete('talk_polls')
				->where($delete->expr()->in('id', $delete->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
			$delete->executeStatement();
		}

		return count($expiredPollIds);
	}

	private function copyExpireDateFromMessages(): void {
		$select = $this->connection->getQueryBuilder();
		$select->select('a.id', 'a.object_type', 'c.expire_date', 'c.message')
			->from('talk_attachments', 'a')
			->innerJoin('a', 'comments', 'c', $select->expr()->eq('a.message_id', 'c.id'))
			->where($select->expr()->eq('c.object_type', $select->createNamedParameter('chat')))
			->andWhere($select->expr()->isNotNull('c.expire_date'))
			->andWhere($select->expr()->isNull('a.expire_date'))
			->andWhere($select->expr()->gt('a.id', $select->createParameter('offset')))
			->orderBy('a.id', 'ASC')
			->setMaxResults(self::BATCH_SIZE);

		$updateAttachment = $this->connection->getQueryBuilder();
		$updateAttachment->update('talk_attachments')
			->set('expire_date', $updateAttachment->createParameter('expire_date'))
			->where($updateAttachment->expr()->eq('id', $updateAttachment->createParameter('id')));

		$updatePoll = $this->connection->getQueryBuilder();
		$updatePoll->update('talk_polls')
			->set('expire_date', $updatePoll->createParameter('expire_date'))
			->where($updatePoll->expr()->eq('id', $updatePoll->createParameter('id')));

		$offset = 0;
		do {
			$select->setParameter('offset', $offset, IQueryBuilder::PARAM_INT);
			$result = $select->executeQuery();
			$rows = $result->fetchAllAssociative();
			$result->closeCursor();

			$this->connection->beginTransaction();
			try {
				foreach ($rows as $row) {
					$offset = (int)$row['id'];
					$expireDate = new \DateTime((string)$row['expire_date']);

					$updateAttachment->setParameter('expire_date', $expireDate, IQueryBuilder::PARAM_DATETIME_MUTABLE)
						->setParameter('id', $offset, IQueryBuilder::PARAM_INT)
						->executeStatement();

					if ($row['object_type'] !== Attachment::TYPE_POLL) {
						continue;
					}

					$message = json_decode((string)$row['message'], true);
					$pollId = (int)($message['parameters']['objectId'] ?? 0);
					if ($pollId > 0) {
						$updatePoll->setParameter('expire_date', $expireDate, IQueryBuilder::PARAM_DATETIME_MUTABLE)
							->setParameter('id', $pollId, IQueryBuilder::PARAM_INT)
							->executeStatement();
					}
				}
			} catch (\Throwable $e) {
				$this->connection->rollBack();
				throw $e;
			}
			$this->connection->commit();
		} while (count($rows) === self::BATCH_SIZE);
	}
}
