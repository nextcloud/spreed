<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\MatrixAccount;

use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;

#[AsCommand(
	name: 'talk:matrix-account:list',
	description: 'List the Matrix accounts linked by users, 1000 per run',
	supportsOutputFormat: true,
)]
class ListAccounts {
	public function __construct(
		private readonly AccountService $accountService,
		private readonly HomeserverService $homeserverService,
	) {
	}

	public function __invoke(
		IOutput $output,
		#[Option(description: 'Continue after this user id, use the last user id of the previous run')]
		string $offset = '',
		#[Option(description: 'Only list accounts on the homeserver with this server name, e.g. example.org', suggestedValues: [self::class, 'suggestServerNames'])]
		string $homeserver = '',
	): ExitCode {
		$homeserverId = '';
		if ($homeserver !== '') {
			try {
				$homeserverId = (string)$this->homeserverService->getByServerName($homeserver)->getId();
			} catch (DoesNotExistException) {
				$output->writeln('<error>Homeserver ' . $homeserver . ' not found</error>');
				return ExitCode::Invalid;
			}
		}

		$output->writeTableInOutputFormat(array_map(
			static fn (Account $account): array => ['userId' => $account->getUserId()] + $account->jsonSerialize(),
			$this->accountService->getAll($offset, 1000, $homeserverId),
		));
		return ExitCode::Success;
	}

	/** @return list<string> */
	public function suggestServerNames(): array {
		return array_map(static fn (Homeserver $homeserver): string => $homeserver->getServerName(), $this->homeserverService->getAll());
	}
}
