<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\MatrixHomeserver;

use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;

#[AsCommand(
	name: 'talk:matrix-homeserver:add',
	description: 'Add a Matrix homeserver users may link accounts on',
)]
class Add {
	public function __construct(
		private readonly HomeserverService $homeserverService,
	) {
	}

	public function __invoke(
		IOutput $output,
		#[Argument(name: 'server-name', description: 'Matrix server name, e.g. example.org')]
		string $serverName,
		#[Option(description: 'Label shown to users, defaults to the server name')]
		string $name = '',
		#[Option(name: 'base-url', description: 'Client API base URL, skips .well-known discovery')]
		string $baseUrl = '',
	): ExitCode {
		try {
			$homeserver = $this->homeserverService->add($name, $serverName, $baseUrl !== '' ? $baseUrl : null);
		} catch (\InvalidArgumentException $e) {
			$output->writeln('<error>' . match ($e->getMessage()) {
				'exists' => 'A homeserver with this name is already configured',
				'server_name' => 'Server name is required',
				default => $e->getMessage(),
			} . '</error>');
			return ExitCode::Invalid;
		} catch (MatrixException $e) {
			$output->writeln('<error>Homeserver error: ' . $e->getMessage() . '</error>');
			return ExitCode::Failure;
		}

		$output->writeln('<info>Added ' . $homeserver->getServerName() . ' (' . $homeserver->getBaseUrl() . ')</info>');
		return ExitCode::Success;
	}
}
