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
class Version19000Date20240709183937 extends SimpleMigrationStep {
	public function __construct(
		protected IDBConnection $connection,
	) {
	}


	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->update('talk_invitations')
			->set('remote_server_url', $query->func()->concat($query->createNamedParameter('https://'), 'remote_server_url'))
			->where($query->expr()->notLike('remote_server_url', $query->createNamedParameter(
				$this->connection->escapeLikeParameter('http://'). '%'
			)))
			->andWhere($query->expr()->notLike('remote_server_url', $query->createNamedParameter(
				$this->connection->escapeLikeParameter('https://'). '%'
			)));
		$query->executeStatement();
	}
}
