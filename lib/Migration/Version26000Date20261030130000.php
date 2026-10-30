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
use OCP\Migration\Attributes\CreateTable;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * What a history import wrote: conversations, messages, participants,
 * placeholder actors and file shares by the importing app's refs
 */
#[CreateTable(table: 'talk_import_refs', columns: ['id', 'app', 'kind', 'ref_hash', 'ref', 'room_id', 'object_id', 'parent_id', 'sha256', 'state', 'display_name', 'owner_id', 'details'], description: 'References of objects written by a history import')]
class Version26000Date20261030130000 extends SimpleMigrationStep {
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

		if ($schema->hasTable('talk_import_refs')) {
			return null;
		}

		$table = $schema->createTable('talk_import_refs');
		$table->addColumn('id', Types::BIGINT, [
			'notnull' => true,
			'unsigned' => true,
			'length' => 20,
		]);
		$table->addColumn('app', Types::STRING, [
			'notnull' => true,
			'length' => 32,
		]);
		$table->addColumn('kind', Types::STRING, [
			'notnull' => true,
			'length' => 16,
		]);
		$table->addColumn('ref_hash', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('ref', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);
		$table->addColumn('room_id', Types::BIGINT, [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
			'length' => 20,
		]);
		$table->addColumn('object_id', Types::BIGINT, [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
			'length' => 20,
		]);
		$table->addColumn('parent_id', Types::BIGINT, [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
			'length' => 20,
		]);
		$table->addColumn('sha256', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);
		$table->addColumn('state', Types::SMALLINT, [
			'notnull' => true,
			'default' => 0,
			'unsigned' => true,
		]);
		$table->addColumn('display_name', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);
		$table->addColumn('owner_id', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);
		$table->addColumn('details', Types::TEXT, [
			'notnull' => false,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['kind', 'ref_hash', 'app'], 'tir_ref');
		$table->addIndex(['room_id', 'kind', 'object_id'], 'tir_room_object');

		return $schema;
	}
}
