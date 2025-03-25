<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Migration;

use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2001Date20170707115443 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $db,
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
		$table = $schema->getTable('spreedme_room_participants');

		if (!$table->hasColumn('participantType')) {
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

		$query = $this->db->getQueryBuilder();

		$query->selectAlias($query->createFunction('COUNT(*)'), 'num_rooms')
			->from('spreedme_rooms');
		$result = $query->executeQuery();
		$return = (int)$result->fetch();
		$result->closeCursor();
		$numRooms = (int)$return['num_rooms'];

		if ($numRooms === 0) {
			return;
		}

		$query->select('id')
			->from('spreedme_rooms')
			->where($query->expr()->eq('type', $query->createNamedParameter(Room::TYPE_ONE_TO_ONE)));
		$result = $query->executeQuery();

		$one2oneRooms = [];
		while ($row = $result->fetch()) {
			$one2oneRooms[] = (int)$row['id'];
		}
		$result->closeCursor();

		if (!empty($one2oneRooms)) {
			$owners = $this->makeOne2OneParticipantsOwners($one2oneRooms);
			$output->info('Made ' . $owners . ' users owner of their one2one calls');
		}

		if (count($one2oneRooms) !== $numRooms) {
			$moderators = $this->makeGroupParticipantsModerators($one2oneRooms);
			$output->info('Made ' . $moderators . ' users moderators in group calls');
		}
	}

	/**
	 * @param int[] $one2oneRooms List of one2one room ids
	 * @return int Number of updated participants
	 */
	protected function makeOne2OneParticipantsOwners(array $one2oneRooms): int {
		$update = $this->db->getQueryBuilder();

		if ($this->db->getDatabaseProvider() !== IDBConnection::PLATFORM_POSTGRES) {
			$update->update('spreedme_room_participants')
				->set('participantType', $update->createNamedParameter(Participant::OWNER))
				->where($update->expr()->in('roomId', $update->createNamedParameter($one2oneRooms, IQueryBuilder::PARAM_INT_ARRAY)));
		} else {
			$update->update('spreedme_room_participants')
				->set('participanttype', $update->createNamedParameter(Participant::OWNER))
				->where($update->expr()->in('roomId', $update->createNamedParameter($one2oneRooms, IQueryBuilder::PARAM_INT_ARRAY)));
		}

		return $update->executeStatement();
	}

	/**
	 * @param int[] $one2oneRooms List of one2one room ids which should not be touched
	 * @return int Number of updated participants
	 */
	protected function makeGroupParticipantsModerators(array $one2oneRooms): int {
		$update = $this->db->getQueryBuilder();

		if ($this->db->getDatabaseProvider() !== IDBConnection::PLATFORM_POSTGRES) {
			$update->update('spreedme_room_participants')
				->set('participantType', $update->createNamedParameter(Participant::MODERATOR))
				->where($update->expr()->nonEmptyString('userId'));
		} else {
			$update->update('spreedme_room_participants')
				->set('participanttype', $update->createNamedParameter(Participant::MODERATOR))
				->where($update->expr()->nonEmptyString('userId'));
		}

		if (!empty($one2oneRooms)) {
			$update->andWhere($update->expr()->notIn('roomId', $update->createNamedParameter($one2oneRooms, IQueryBuilder::PARAM_INT_ARRAY)));
		}

		return $update->executeStatement();
	}
}
