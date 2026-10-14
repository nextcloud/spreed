<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\AppFramework\Services\IAppConfig;
use OCP\DB\Exception;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version23001Date20260918142246 extends SimpleMigrationStep {

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

		$query = $this->connection->getQueryBuilder();
		$query->select('id', 'phone_number')
			->from('talk_phone_numbers');

		$phoneNumbers = [];
		$result = $query->executeQuery();
		while ($row = $result->fetchAssociative()) {
			$phoneNumbers[(int)$row['id']] = (string)$row['phone_number'];
		}
		$result->closeCursor();

		$update = $this->connection->getQueryBuilder();
		$update->update('talk_phone_numbers')
			->set('phone_number', $update->createParameter('phone_number'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		foreach ($phoneNumbers as $id => $phoneNumber) {
			if (strlen($phoneNumber) < 6
				// Numbers added before the leading + was stripped already have it
				|| str_starts_with($phoneNumber, '+')
				|| str_starts_with($phoneNumber, $dialoutPrefix)) {
				continue;
			}

			$update->setParameter('phone_number', $dialoutPrefix . $phoneNumber)
				->setParameter('id', $id, IQueryBuilder::PARAM_INT);
			try {
				$update->executeStatement();
			} catch (Exception $e) {
				if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $e;
				}
				$output->warning('Phone number ' . $phoneNumber . ' was not migrated, because ' . $dialoutPrefix . $phoneNumber . ' is already assigned');
			}
		}
	}
}
