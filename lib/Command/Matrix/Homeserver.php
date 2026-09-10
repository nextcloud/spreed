<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Matrix;

use Nextcloud\Matrix\Exception\MatrixException;
use OC\Core\Command\Base;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Homeserver extends Base {
	public function __construct(
		private readonly HomeserverService $homeserverService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();
		$this
			->setName('talk:matrix:homeserver')
			->setDescription('Manage the Matrix homeservers users may link accounts on')
			->addArgument('action', InputArgument::REQUIRED, 'add | list | remove | test')
			->addArgument('server-name', InputArgument::OPTIONAL, 'Matrix server name (add/remove/test), e.g. example.org')
			->addOption('name', null, InputOption::VALUE_REQUIRED, 'Label shown to users (add)')
			->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Client API base URL override, skips .well-known discovery (add)');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$action = (string)$input->getArgument('action');
		$serverName = (string)($input->getArgument('server-name') ?? '');
		try {
			switch ($action) {
				case 'list':
					$this->writeTableInOutputFormat($input, $output, array_map(static fn ($hs) => $hs->jsonSerialize() + ['specVersions' => implode(', ', $hs->jsonSerialize()['specVersions'])], $this->homeserverService->getAll()));
					return 0;
				case 'add':
					$homeserver = $this->homeserverService->add((string)($input->getOption('name') ?? ''), $serverName, $input->getOption('base-url'));
					$output->writeln('<info>Added ' . $homeserver->getServerName() . ' (' . $homeserver->getBaseUrl() . '), id ' . $homeserver->getId() . '</info>');
					return 0;
				case 'remove':
					$homeserver = $this->find($serverName);
					$this->homeserverService->remove($homeserver->getId());
					$output->writeln('<info>Removed ' . $serverName . '</info>');
					return 0;
				case 'test':
					$homeserver = $this->find($serverName);
					$homeserver = $this->homeserverService->refreshVersions($homeserver->getId());
					$output->writeln('<info>' . $homeserver->getBaseUrl() . ' speaks Matrix ' . implode(', ', $homeserver->jsonSerialize()['specVersions']) . '</info>');
					return 0;
				default:
					$output->writeln('<error>Unknown action ' . $action . '</error>');
					return 1;
			}
		} catch (\InvalidArgumentException $e) {
			$output->writeln('<error>' . match ($e->getMessage()) {
				'exists' => 'A homeserver with this name is already configured',
				'accounts' => 'Accounts are still linked to this homeserver',
				'server_name' => 'Server name is required',
				default => $e->getMessage(),
			} . '</error>');
			return 1;
		} catch (MatrixException $e) {
			$output->writeln('<error>Homeserver error: ' . $e->getMessage() . '</error>');
			return 2;
		}
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private function find(string $serverName): \OCA\Talk\Matrix\Model\Homeserver {
		foreach ($this->homeserverService->getAll() as $homeserver) {
			if ($homeserver->getServerName() === strtolower(trim($serverName)) || (string)$homeserver->getId() === $serverName) {
				return $homeserver;
			}
		}
		throw new \InvalidArgumentException('Homeserver ' . $serverName . ' not found');
	}
}
