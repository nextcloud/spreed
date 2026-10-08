<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\MatrixHomeserver;

use OCA\Talk\Matrix\Service\HomeserverService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;

#[AsCommand(
	name: 'talk:matrix-homeserver:remove',
	description: 'Remove a Matrix homeserver',
)]
class Remove {
	public function __construct(
		private readonly HomeserverService $homeserverService,
	) {
	}

	public function __invoke(
		IOutput $output,
		#[Argument(name: 'server-name', description: 'Matrix server name, e.g. example.org', suggestedValues: [self::class, 'suggestServerNames'])]
		string $serverName,
	): ExitCode {
		try {
			$homeserver = $this->homeserverService->getByServerName($serverName);
			$this->homeserverService->remove((string)$homeserver->getId());
		} catch (DoesNotExistException) {
			$output->writeln('<error>Homeserver ' . $serverName . ' not found</error>');
			return ExitCode::Invalid;
		}

		$output->writeln('<info>Removed ' . $homeserver->getServerName() . '</info>');
		return ExitCode::Success;
	}

	/** @return list<string> */
	public function suggestServerNames(): array {
		return array_map(static fn ($homeserver) => $homeserver->getServerName(), $this->homeserverService->getAll());
	}
}
