<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\Poll;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\AddIndex;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\Attributes\IndexType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Store the expiration of the chat message on attachments and polls,
 * so they can be cleaned up together with the message.
 */
#[AddColumn(table: 'talk_attachments', name: 'expire_date', type: ColumnType::DATETIME)]
#[AddIndex(table: 'talk_attachments', type: IndexType::INDEX, description: 'index on expire date')]
#[AddColumn(table: 'talk_polls', name: 'expire_date', type: ColumnType::DATETIME)]
#[AddIndex(table: 'talk_polls', type: IndexType::INDEX, description: 'index on expire date')]
class Version26000Date20261031120000 extends SimpleMigrationStep {
	private const BATCH_SIZE = 1000;

	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$changed = false;
		foreach (['talk_attachments' => 'talk_attach_expire', 'talk_polls' => 'talk_poll_expire'] as $tableName => $indexName) {
			$table = $schema->getTable($tableName);
			if ($table->hasColumn('expire_date')) {
				continue;
			}

			$table->addColumn('expire_date', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->addIndex(['expire_date'], $indexName);
			$changed = true;
		}

		return $changed ? $schema : null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->deleteAttachmentsOfExpiredMessages();
		$this->copyExpireDateFromMessages();
	}

	/**
	 * Messages that expired before the expire date was stored on
	 * attachments and polls are gone already, so remove the leftovers.
	 */
	private function deleteAttachmentsOfExpiredMessages(): void {
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
			}
		} while (count($rows) === self::BATCH_SIZE);

		foreach (array_keys($roomsWithExpiredPolls) as $roomId) {
			$this->deletePollsWithoutMessage($roomId);
		}
	}

	/**
	 * The poll id is only stored in the chat message, so a poll whose
	 * message expired is detected by no longer being referenced by any
	 * remaining poll message of the room.
	 */
	private function deletePollsWithoutMessage(int $roomId): void {
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
