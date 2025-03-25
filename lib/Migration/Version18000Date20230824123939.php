<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version18000Date20230824123939 extends SimpleMigrationStep {
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

		$modified = false;
		$table = $schema->getTable('talk_bots_server');
		if (!$table->hasIndex('talk_bots_server_urlhash')) {
			$table->addUniqueIndex(['url_hash'], 'talk_bots_server_urlhash');
			$modified = true;
		}
		if (!$table->hasIndex('talk_bots_server_secret')) {
			$table->addUniqueIndex(['secret'], 'talk_bots_server_secret');
			$modified = true;
		}

		$table = $schema->getTable('talk_bots_conversation');
		if (!$table->hasIndex('talk_bots_convo_uniq')) {
			$table->addUniqueIndex(['bot_id', 'token'], 'talk_bots_convo_uniq');
			$modified = true;
		}

		return $modified ? $schema : null;
	}
}
