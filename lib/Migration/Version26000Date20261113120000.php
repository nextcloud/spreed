<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCA\Talk\BackgroundJob\IndexLinkAttachments;
use OCP\BackgroundJob\IJobList;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\Attributes\AddIndex;
use OCP\Migration\Attributes\DropIndex;
use OCP\Migration\Attributes\IndexType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Replace the index to list attachments of a room with one ordered by message id
 * and schedule indexing links of chat messages sent before the upgrade
 */
#[AddIndex(table: 'talk_attachments', type: IndexType::INDEX, description: 'index on room, object type and message id')]
#[DropIndex(table: 'talk_attachments', type: IndexType::INDEX, description: 'index on room and object type, covered by the new index')]
class Version26000Date20261113120000 extends SimpleMigrationStep {
	public function __construct(
		protected IJobList $jobList,
		protected IDBConnection $connection,
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

		$table = $schema->getTable('talk_attachments');
		$changed = false;
		if (!$table->hasIndex('talk_attach_room_msg')) {
			$changed = true;
			$table->addIndex(['room_id', 'object_type', 'message_id'], 'talk_attach_room_msg');
		}

		if ($table->hasIndex('objects_in_room')) {
			$changed = true;
			$table->dropIndex('objects_in_room');
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
		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->max('id'))
			->from('comments');
		$maxId = $query->executeQuery()->fetchOne();

		if ($maxId !== null && $maxId !== false) {
			// Newer messages are indexed when they are sent
			$this->jobList->add(IndexLinkAttachments::class, ['beforeId' => (int)$maxId + 1]);
		}
	}
}
