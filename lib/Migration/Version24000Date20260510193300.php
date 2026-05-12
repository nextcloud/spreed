<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Override;

class Version24000Date20260510193300 extends SimpleMigrationStep {

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

		$table = $schema->getTable('talk_rooms');

		if (!$table->hasColumn('last_metadata_activity')) {
			$table->addColumn('last_metadata_activity', Types::DATETIME, [
				'notnull' => false,
			]);
<<<<<<< HEAD
<<<<<<< HEAD
			$table->addIndex(['last_metadata_activity'], 'talkroom_lastmetadataactive');

		}
		
		$table = $schema->getTable('talk_threads');

		if (!$table->hasColumn('last_metadata_activity')) {
			$table->addColumn('last_metadata_activity', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->addIndex(['last_metadata_activity'], 'talkthread_lastmetadataactive');
=======
			$table->addIndex(['last_metadata_activity'], 'talkthread_lastmetadataactive');
=======
			$table->addIndex(['last_metadata_activity'], 'talkroom_lastmetadataactive');
>>>>>>> a798ce9366 (change(conversations): change behavior in all necessary places for Rooms and Threads to use / set lastMetadataActivity instaed of lastActivity where appropriate, i.e. where system messages are signalled and not real chat messages.)

>>>>>>> 2ed52550f0 (feature(api): Add a new field and corresponding functionality for lastMetadaActivity for Rooms and Threads, similar to lastActivity. This also adds a database migration for the oc_talk_rooms and oc_talk_threads tables as well. Functions are introduced and prepared for later use. The use of the field lastActivity to signal when the last real message in a conversation appeared remains unchanged to keep the API stable. The intended use of this feature is to better distinguish between real messages (lastActivity) to notify and bump conversations in the thread list to the top, and other status / metadata related messages (lastMetadataActivity) like room state and participant list changes that shall get synced and be updated in the clients, but not trigger an activity bump of its conversations in the thread list.)
		}
		
		$table = $schema->getTable('talk_threads');

		if (!$table->hasColumn('last_metadata_activity')) {
			$table->addColumn('last_metadata_activity', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->addIndex(['last_metadata_activity'], 'talkthread_lastmetadataactive');
		}

		return $schema;
	}
	
	/**
 	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) : void {
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> a798ce9366 (change(conversations): change behavior in all necessary places for Rooms and Threads to use / set lastMetadataActivity instaed of lastActivity where appropriate, i.e. where system messages are signalled and not real chat messages.)
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_rooms')
			->set('last_metadata_activity', 'last_activity');
		$update->executeStatement();
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_threads')
			->set('last_metadata_activity', 'last_activity');
		$update->executeStatement();
<<<<<<< HEAD
=======
	   $update = $this->connection->getQueryBuilder();
	   $update->update('talk_rooms')
               ->set('last_metadata_activity', 'last_activity');
       $update->executeStatement();
>>>>>>> 2ed52550f0 (feature(api): Add a new field and corresponding functionality for lastMetadaActivity for Rooms and Threads, similar to lastActivity. This also adds a database migration for the oc_talk_rooms and oc_talk_threads tables as well. Functions are introduced and prepared for later use. The use of the field lastActivity to signal when the last real message in a conversation appeared remains unchanged to keep the API stable. The intended use of this feature is to better distinguish between real messages (lastActivity) to notify and bump conversations in the thread list to the top, and other status / metadata related messages (lastMetadataActivity) like room state and participant list changes that shall get synced and be updated in the clients, but not trigger an activity bump of its conversations in the thread list.)
=======
>>>>>>> a798ce9366 (change(conversations): change behavior in all necessary places for Rooms and Threads to use / set lastMetadataActivity instaed of lastActivity where appropriate, i.e. where system messages are signalled and not real chat messages.)
	}	

}
