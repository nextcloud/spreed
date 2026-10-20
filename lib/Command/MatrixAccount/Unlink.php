<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\MatrixAccount;

use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Service\AccountService;
use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;

#[AsCommand(
	name: 'talk:matrix-account:unlink',
	description: 'Unlink the Matrix account of a user and log Talk out on the homeserver',
)]
class Unlink {
	public function __construct(
		private readonly AccountService $accountService,
	) {
	}

	public function __invoke(
		IOutput $output,
		#[Argument(name: 'user-id', description: 'Nextcloud user id', suggestedValues: [self::class, 'suggestUserIds'])]
		string $userId,
	): ExitCode {
		$account = $this->accountService->getForUser($userId);
		if ($account === null) {
			$output->writeln('<error>User ' . $userId . ' has no linked Matrix account</error>');
			return ExitCode::Invalid;
		}

		$this->accountService->unlink($account);
		$output->writeln('<info>Unlinked ' . $account->getMxid() . ' from ' . $userId . '</info>');
		return ExitCode::Success;
	}

	/** @return list<string> */
	public function suggestUserIds(): array {
		return array_map(static fn (Account $account): string => $account->getUserId(), $this->accountService->getAll());
	}
}
