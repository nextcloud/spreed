<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version16000Date20230502145340 extends SimpleMigrationStep {
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
		$query = $this->connection->getQueryBuilder();

		$query->update('talk_attendees')
			->set('permissions', $query->createNamedParameter(Attendee::PERMISSIONS_DEFAULT, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('participant_type', $query->createNamedParameter(Participant::MODERATOR)))
			->orWhere($query->expr()->eq('participant_type', $query->createNamedParameter(Participant::GUEST_MODERATOR)));
		$fixed = $query->executeStatement();

		$output->info('Fixed permissions of ' . $fixed . ' moderators');
	}
}
