<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\AppFramework\Services\IAppConfig;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version24000Date20260918142246 extends SimpleMigrationStep {

	public function __construct(
		private readonly IDBConnection $connection,
		private readonly IAppConfig $appConfig,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$dialoutPrefix = $this->appConfig->getAppValueString('sip_bridge_dialout_prefix', '+');

		if ($dialoutPrefix === '') {
			return;
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_phone_numbers')
			->set('phone_number', $update->func()->concat(
				$update->createNamedParameter($dialoutPrefix, IQueryBuilder::PARAM_STR),
				'phone_number',
			))
			->where($update->expr()->gte(
				$update->func()->charLength('phone_number'),
				$update->createNamedParameter(6, IQueryBuilder::PARAM_INT))
			);

		$update->executeStatement();
	}
}
