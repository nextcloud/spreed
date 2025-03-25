<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20000Date20240717180417 extends SimpleMigrationStep {
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

		// Remove redundant index ta_room from talk_attendees
		$table = $schema->getTable('talk_attendees');
		if ($table->hasIndex('ta_room')) {
			$table->dropIndex('ta_room');
		}

		// Remove redundant index talk_bots_convo_id from talk_bots_conversation
		$table = $schema->getTable('talk_bots_conversation');
		if ($table->hasIndex('talk_bots_convo_id')) {
			$table->dropIndex('talk_bots_convo_id');
		}

		return $schema;
	}
}
