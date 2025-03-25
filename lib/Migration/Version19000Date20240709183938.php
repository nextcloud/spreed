<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Heal federation from before Nextcloud 29.0.4 which sends requests
 * without the protocol on the remote in case it is https://
 */
class Version19000Date20240709183938 extends SimpleMigrationStep {
	public function __construct(
		protected IDBConnection $connection,
	) {
	}


	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->addMissingProtocol('talk_invitations', 'remote_server_url');
		$this->addMissingProtocol('talk_proxy_messages', 'remote_server_url');
		$this->addMissingProtocol('talk_rooms', 'remote_server');
	}

	protected function addMissingProtocol(string $table, string $column): void {
		$query = $this->connection->getQueryBuilder();
		$query->update($table)
			->set($column, $query->func()->concat($query->createNamedParameter('https://'), $column))
			->where($query->expr()->notLike($column, $query->createNamedParameter(
				$this->connection->escapeLikeParameter('http://') . '%'
			)))
			->andWhere($query->expr()->notLike($column, $query->createNamedParameter(
				$this->connection->escapeLikeParameter('https://') . '%'
			)))
			->andWhere($query->expr()->nonEmptyString($column));
		$query->executeStatement();
	}
}
