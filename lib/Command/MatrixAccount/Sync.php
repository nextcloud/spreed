<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\MatrixAccount;

use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Sync\SyncService;
use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;

#[AsCommand(
	name: 'talk:matrix-account:sync',
	description: 'Sync the Matrix rooms of a user now',
)]
class Sync {
	public function __construct(
		private readonly AccountService $accountService,
		private readonly SyncService $syncService,
	) {
	}

	public function __invoke(
		IOutput $output,
		#[Argument(name: 'user-id', description: 'Nextcloud user id', suggestedValues: [self::class, 'suggestUserIds'])]
		string $userId,
		#[Option(description: 'Forget the sync position and sync all rooms again')]
		bool $reset = false,
		#[Option(description: 'Seconds to spend syncing at most')]
		int $budget = 60,
	): ExitCode {
		$account = $this->accountService->getForUser($userId);
		if ($account === null) {
			$output->writeln('<error>User ' . $userId . ' has no linked Matrix account</error>');
			return ExitCode::Invalid;
		}

		if ($reset) {
			$this->syncService->resetSyncPosition($account);
		}

		$stats = $this->syncService->syncAccount($account, $budget);
		if ($stats === null) {
			$output->writeln('<error>The account needs a new login or is synced by another process right now</error>');
			return ExitCode::Failure;
		}

		$output->writeln('<info>Synced ' . $stats['messages'] . ' new messages in ' . $stats['rooms'] . ' rooms in ' . $stats['batches'] . ' batches</info>');
		if ($account->getLastError() !== null) {
			$output->writeln('<error>' . $account->getLastError() . '</error>');
			return ExitCode::Failure;
		}
		return ExitCode::Success;
	}

	/** @return list<string> */
	public function suggestUserIds(): array {
		return array_map(static fn (Account $account): string => $account->getUserId(), $this->accountService->getAll());
	}
}
