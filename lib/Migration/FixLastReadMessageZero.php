<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use OCA\Talk\Chat\ChatManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * In previous versions of Talk, the value 0 was used to mark a chat as
 * completely unread. This meant that the next time a client/browser loaded the
 * chat it would start all from the beginning. However, there were various
 * issues over the time that made the frontend getting `null` or `undefined`
 * into the wrong place and then accidentally loading 8 years of chat from the
 * beginning. So it was agreed that the frontend manually blocks loading with
 * last-read-message=0 and the special cases use -2 for this situation.
 */
class FixLastReadMessageZero implements IRepairStep {
	public function __construct(
		protected IDBConnection $connection,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Fix the namespace in database tables';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_attendees')
			/**
			 * -2 is {@see ChatManager::UNREAD_FIRST_MESSAGE}, but we can't use
			 * it in update code, because ChatManager is already loaded with the
			 * previous implementation.
			 */
			->set('last_read_message', $update->createNamedParameter(-2, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('last_read_message', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		$updatedEntries = $update->executeStatement();
		if ($updatedEntries) {
			$output->info($updatedEntries . ' attendees have been updated.');
		}
	}
}
