<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\MatrixAccount;

use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Service\AccountService;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;

#[AsCommand(
	name: 'talk:matrix-account:list',
	description: 'List the Matrix accounts linked by users',
	supportsOutputFormat: true,
)]
class ListAccounts {
	public function __construct(
		private readonly AccountService $accountService,
	) {
	}

	public function __invoke(
		IOutput $output,
	): ExitCode {
		$output->writeTableInOutputFormat(array_map(
			static fn (Account $account): array => ['userId' => $account->getUserId()] + $account->jsonSerialize(),
			$this->accountService->getAll(),
		));
		return ExitCode::Success;
	}
}
