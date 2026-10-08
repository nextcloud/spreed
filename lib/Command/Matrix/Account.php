<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Matrix;

use OC\Core\Command\Base;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\LifecycleService;
use OCA\Talk\Matrix\Sync\SyncService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Account extends Base {
	public function __construct(
		private readonly AccountService $accountService,
		private readonly LifecycleService $lifecycleService,
		private readonly SyncService $syncService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();
		$this
			->setName('talk:matrix:account')
			->setDescription('List, sync or unlink Matrix accounts of users')
			->addArgument('action', InputArgument::REQUIRED, 'list | sync | unlink | request-keys')
			->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Nextcloud user id (sync/unlink)')
			->addOption('budget', null, InputOption::VALUE_REQUIRED, 'Seconds to spend syncing', '20')
			->addOption('reset', null, InputOption::VALUE_NONE, 'Forget the sync position first (full re-sync, existing conversations are kept and deduplicated)');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$action = (string)$input->getArgument('action');
		if ($action === 'list') {
			$rows = array_map(static fn ($a) => $a->toUserArray() + ['userId' => $a->getUserId(), 'lastError' => (string)$a->getLastError()], $this->accountService->getAll());
			$this->writeTableInOutputFormat($input, $output, $rows);
			return 0;
		}
		$userId = (string)($input->getOption('user') ?? '');
		if ($userId === '') {
			$output->writeln('<error>--user is required</error>');
			return 1;
		}
		$account = $this->accountService->getForUser($userId);
		if ($account === null) {
			$output->writeln('<error>User ' . $userId . ' has no linked Matrix account</error>');
			return 1;
		}
		switch ($action) {
			case 'sync':
				if ($input->getOption('reset')) {
					$this->syncService->resetSyncToken($account);
				}
				$stats = $this->syncService->syncAccount($account, (int)$input->getOption('budget'), 0);
				if ($stats === null) {
					$output->writeln('<comment>Account is not active or currently locked by another sync</comment>');
					return 1;
				}
				$output->writeln('<info>' . $stats['batches'] . ' batches, ' . $stats['rooms'] . ' rooms, ' . $stats['messages'] . ' new messages</info>');
				$account = $this->accountService->getById($account->getId());
				if ($account?->getLastError() !== null) {
					$output->writeln('<error>' . $account->getLastError() . '</error>');
					return 2;
				}
				return 0;
			case 'request-keys':
				$n = \OCP\Server::get(\OCA\Talk\Matrix\Service\CryptoService::class)->rerequestMissingKeys($account);
				$output->writeln('<info>Requested keys for ' . $n . ' session(s) from the other devices</info>');
				return 0;
			case 'unlink':
				$this->lifecycleService->unlink($account);
				$output->writeln('<info>Unlinked ' . $account->getMxid() . ' from ' . $userId . '</info>');
				return 0;
			default:
				$output->writeln('<error>Unknown action ' . $action . '</error>');
				return 1;
		}
	}
}
