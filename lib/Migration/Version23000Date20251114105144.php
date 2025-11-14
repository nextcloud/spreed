<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Migration;

use Closure;
use OCA\Talk\Participant;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Persist the previous default for group notifications for installations on update.
 */
class Version23000Date20251114105144 extends SimpleMigrationStep {
	public function __construct(
		protected readonly IAppConfig $appConfig,
	) {
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Persist the previous default for group notifications for installations on update.
		$previous = $this->appConfig->getAppValueInt('default_group_notification', Participant::NOTIFY_DEFAULT);
		if ($previous === Participant::NOTIFY_DEFAULT) {
			$this->appConfig->setAppValueInt(
				'default_group_notification',
				Participant::NOTIFY_MENTION,
			);
		}
	}
}
