<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Migration;

use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2001Date20171026134605 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $connection,
		protected IConfig $config,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 13.0.0
	 */
	#[\Override]
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		/**
		 * Table had to be rebuild because it was missing a primary key
		 * @see Version11000Date20201209142525
		 *
		 * if (!$schema->hasTable('talk_signaling')) {
		 * $table = $schema->createTable('talk_signaling');
		 *
		 * $table->addColumn('sender', Types::STRING, [
		 * 'notnull' => true,
		 * 'length' => 255,
		 * ]);
		 * $table->addColumn('recipient', Types::STRING, [
		 * 'notnull' => true,
		 * 'length' => 255,
		 * ]);
		 * $table->addColumn('message', Types::TEXT, [
		 * 'notnull' => true,
		 * ]);
		 * $table->addColumn('timestamp', Types::INTEGER, [
		 * 'notnull' => true,
		 * 'length' => 11,
		 * ]);
		 *
		 * $table->addIndex(['recipient', 'timestamp'], 'ts_recipient_time');
		 * }
		 */

		if (!$schema->hasTable('talk_rooms')) {
			$table = $schema->createTable('talk_rooms');

			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('token', Types::STRING, [
				'notnull' => false,
				'length' => 32,
				'default' => '',
			]);
			$table->addColumn('type', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('password', Types::STRING, [
				'notnull' => false,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('activeSince', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->addColumn('activeGuests', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['token'], 'tr_room_token');
		}

		if (!$schema->hasTable('talk_participants')) {
			$table = $schema->createTable('talk_participants');

			$table->addColumn('userId', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('roomId', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('lastPing', Types::INTEGER, [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('sessionId', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('participantType', Types::SMALLINT, [
				'notnull' => true,
				'length' => 6,
				'default' => 0,
			]);
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @since 13.0.0
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		if (version_compare($this->config->getAppValue('spreed', 'installed_version', '0.0.0'), '2.0.0', '<')) {
			// Migrations only work after 2.0.0
			return;
		}

		$roomIdMap = $this->copyRooms();
		$this->copyParticipants($roomIdMap);
		$this->fixNotifications($roomIdMap);
		$this->fixActivities($roomIdMap);
		$this->fixActivityMails($roomIdMap);
	}

	/**
	 * @return int[]
	 */
	protected function copyRooms(): array {
		$roomIdMap = [];

		$insert = $this->connection->getQueryBuilder();
		$insert->insert('talk_rooms')
			->values([
				'name' => $insert->createParameter('name'),
				'token' => $insert->createParameter('token'),
				'type' => $insert->createParameter('type'),
				'password' => $insert->createParameter('password'),
			]);

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms');

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$insert
				->setParameter('name', $row['name'])
				->setParameter('token', $row['token'])
				->setParameter('type', (int)$row['type'], IQueryBuilder::PARAM_INT)
				->setParameter('password', $row['password']);
			$insert->executeStatement();

			$roomIdMap[(int)$row['id']] = $insert->getLastInsertId();
		}
		$result->closeCursor();

		return $roomIdMap;
	}

	/**
	 * @param int[] $roomIdMap
	 */
	protected function copyParticipants(array $roomIdMap): void {
		$insert = $this->connection->getQueryBuilder();
		if ($this->connection->getDatabaseProvider() !== IDBConnection::PLATFORM_POSTGRES) {
			$insert->insert('talk_participants')
				->values([
					'userId' => $insert->createParameter('userId'),
					'roomId' => $insert->createParameter('roomId'),
					'lastPing' => $insert->createParameter('lastPing'),
					'sessionId' => $insert->createParameter('sessionId'),
					'participantType' => $insert->createParameter('participantType'),
				]);
		} else {
			$insert->insert('talk_participants')
				->values([
					'userid' => $insert->createParameter('userId'),
					'roomid' => $insert->createParameter('roomId'),
					'lastping' => $insert->createParameter('lastPing'),
					'sessionid' => $insert->createParameter('sessionId'),
					'participanttype' => $insert->createParameter('participantType'),
				]);
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('spreedme_room_participants');

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			if (!isset($roomIdMap[(int)$row['roomId']])) {
				continue;
			}

			$insert
				->setParameter('userId', $row['userId'])
				->setParameter('roomId', $roomIdMap[(int)$row['roomId']], IQueryBuilder::PARAM_INT)
				->setParameter('lastPing', (int)$row['lastPing'], IQueryBuilder::PARAM_INT)
				->setParameter('sessionId', $row['sessionId'])
			;
			if ($this->connection->getDatabaseProvider() !== IDBConnection::PLATFORM_POSTGRES) {
				$insert->setParameter('participantType', (int)$row['participantType'], IQueryBuilder::PARAM_INT);
			} else {
				$insert->setParameter('participantType', (int)$row['participanttype'], IQueryBuilder::PARAM_INT);
			}
			$insert->executeStatement();
		}
		$result->closeCursor();
	}

	/**
	 * @param int[] $roomIdMap
	 */
	protected function fixNotifications(array $roomIdMap): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('notifications')
			->set('object_id', $update->createParameter('newId'))
			->where($update->expr()->eq('notification_id', $update->createParameter('id')));

		$delete = $this->connection->getQueryBuilder();
		$delete->delete('notifications')
			->where($delete->expr()->eq('notification_id', $delete->createParameter('id')));

		$query = $this->connection->getQueryBuilder();
		$query->select(['notification_id', 'object_id'])
			->from('notifications')
			->where($query->expr()->eq('app', $query->createNamedParameter('spreed')))
			->andWhere($query->expr()->eq('object_type', $query->createNamedParameter('room')));

		try {
			$result = $query->executeQuery();
		} catch (TableNotFoundException $e) {
			return;
		}

		while ($row = $result->fetch()) {
			if (!isset($roomIdMap[(int)$row['object_id']])) {
				$delete
					->setParameter('id', (int)$row['notification_id'])
				;
				$delete->executeStatement();
				continue;
			}

			$update
				->setParameter('id', (int)$row['notification_id'])
				->setParameter('newId', $roomIdMap[(int)$row['object_id']])
			;
			$update->executeStatement();
		}
		$result->closeCursor();
	}

	/**
	 * @param int[] $roomIdMap
	 */
	protected function fixActivities(array $roomIdMap): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('activity')
			->set('object_id', $update->createParameter('newId'))
			->set('subjectparams', $update->createParameter('subjectParams'))
			->where($update->expr()->eq('activity_id', $update->createParameter('id')));

		$delete = $this->connection->getQueryBuilder();
		$delete->delete('activity')
			->where($delete->expr()->eq('activity_id', $delete->createParameter('id')));

		$query = $this->connection->getQueryBuilder();
		$query->select(['activity_id', 'object_id', 'subjectparams'])
			->from('activity')
			->where($query->expr()->eq('app', $query->createNamedParameter('spreed')))
			->andWhere($query->expr()->eq('type', $query->createNamedParameter('spreed')))
			->andWhere($query->expr()->eq('object_type', $query->createNamedParameter('room')));

		try {
			$result = $query->executeQuery();
		} catch (TableNotFoundException|InvalidFieldNameException) {
			return;
		}

		while ($row = $result->fetch()) {
			if (!isset($roomIdMap[(int)$row['object_id']])) {
				$delete
					->setParameter('id', (int)$row['activity_id'])
				;
				$delete->executeStatement();
				continue;
			}

			$params = json_decode($row['subjectparams'], true);

			if (!isset($params['room'])) {
				$delete
					->setParameter('id', (int)$row['activity_id'])
				;
				$delete->executeStatement();
				continue;
			}

			$params['room'] = $roomIdMap[(int)$row['object_id']];

			$update
				->setParameter('id', (int)$row['activity_id'])
				->setParameter('newId', $roomIdMap[(int)$row['object_id']])
				->setParameter('subjectParams', json_encode($params))
			;
			$update->executeStatement();
		}
		$result->closeCursor();
	}

	/**
	 * @param int[] $roomIdMap
	 */
	protected function fixActivityMails(array $roomIdMap): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('activity_mq')
			->set('amq_subjectparams', $update->createParameter('subjectParams'))
			->where($update->expr()->eq('mail_id', $update->createParameter('id')));

		$delete = $this->connection->getQueryBuilder();
		$delete->delete('activity_mq')
			->where($delete->expr()->eq('mail_id', $delete->createParameter('id')));

		$query = $this->connection->getQueryBuilder();
		$query->select(['mail_id', 'amq_subjectparams'])
			->from('activity_mq')
			->where($query->expr()->eq('amq_appid', $query->createNamedParameter('spreed')))
			->andWhere($query->expr()->eq('amq_type', $query->createNamedParameter('spreed')));

		try {
			$result = $query->executeQuery();
		} catch (TableNotFoundException|InvalidFieldNameException) {
			return;
		}

		while ($row = $result->fetch()) {
			$params = json_decode($row['subjectparams'], true);

			if (!isset($params['room']) || !isset($roomIdMap[(int)$params['room']])) {
				$delete
					->setParameter('id', (int)$row['mail_id'])
				;
				$delete->executeStatement();
				continue;
			}

			$params['room'] = $roomIdMap[(int)$params['room']];

			$update
				->setParameter('id', (int)$row['mail_id'])
				->setParameter('subjectParams', json_encode($params))
			;
			$update->executeStatement();
		}
		$result->closeCursor();
	}
}
