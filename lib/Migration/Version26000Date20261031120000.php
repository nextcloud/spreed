<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCA\Talk\Model\Attachment;
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
