<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Share\IShare;
use Override;

/**
 * Add column on rooms if they have at least one attachment
 */
class Version22000Date20250803160923 extends SimpleMigrationStep {
	public function __construct(
		protected IDBConnection $db,
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

		$table = $schema->getTable('talk_rooms');
		if (!$table->hasColumn('has_attachments')) {
			$table->addColumn('has_attachments', Types::SMALLINT, [
				'notnull' => false,
				'default' => 0,
			]);
			return $schema;
		}

		return null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$query = $this->db->getQueryBuilder();
		$query->select('share_with')
			->from('share')
			->where($query->expr()->eq('share_type', $query->createNamedParameter(IShare::TYPE_ROOM)))
			->groupBy('share_with');

		$tokens = [];
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$tokens[] = $row['share_with'];
		}
		$result->closeCursor();

		// Update current conversations with the correct flag
		$chunks = array_chunk($tokens, 1000);
		$update = $this->db->getQueryBuilder();
		$update->update('talk_rooms')
			/** Can not use @see \OCA\Talk\Model\Attachment::ATTACHMENTS_ATLEAST_ONE during update */
			->set('has_attachments', $update->createNamedParameter(1))
			->where($update->expr()->in('token', $update->createParameter('tokens')));

		foreach ($chunks as $chunk) {
			$update->setParameter('tokens', $chunk, IQueryBuilder::PARAM_STR_ARRAY);
			$update->executeStatement();
		}
	}
}
