<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Migration\Attributes\DataCleansing;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[DataCleansing(table: 'appconfig', description: 'delete hosted-signaling-server-account-last-checked app config')]
class Version14000Date20220217115327 extends SimpleMigrationStep {

	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	#[\Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->appConfig->deleteAppValue('hosted-signaling-server-account-last-checked');
	}
}
