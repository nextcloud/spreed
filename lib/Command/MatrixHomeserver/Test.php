<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\MatrixHomeserver;

use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;

#[AsCommand(
	name: 'talk:matrix-homeserver:test',
	description: 'Test the connection to a Matrix homeserver and refresh its supported spec versions',
)]
class Test {
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
			$homeserver = $this->homeserverService->refreshVersions((string)$homeserver->getId());
		} catch (DoesNotExistException) {
			$output->writeln('<error>Homeserver ' . $serverName . ' not found</error>');
			return ExitCode::Invalid;
		} catch (MatrixException $e) {
			$output->writeln('<error>Homeserver error: ' . $e->getMessage() . '</error>');
			return ExitCode::Failure;
		}

		$output->writeln('<info>' . $homeserver->getBaseUrl() . ' speaks Matrix ' . implode(', ', $homeserver->jsonSerialize()['specVersions']) . '</info>');
		return ExitCode::Success;
	}

	/** @return list<string> */
	public function suggestServerNames(): array {
		return array_map(static fn ($homeserver) => $homeserver->getServerName(), $this->homeserverService->getAll());
	}
}
