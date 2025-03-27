<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version21001Date20250327161337 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('talk_phone_numbers')) {
			return null;
		}

		$table = $schema->createTable('talk_phone_numbers');
		$table->addColumn('phone_number', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('actor_id', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);

		$table->setPrimaryKey(['phone_number']);
		$table->addIndex(['actor_id']);
		return $schema;
	}
}
