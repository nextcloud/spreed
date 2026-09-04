<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Matrix;

use OC\Core\Command\Base;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Security\ICrypto;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Re-encrypt all Matrix tokens and key material after the instance `secret`
 * in config.php was rotated: decrypt with the old secret, encrypt with the current one.
 */
class Rekey extends Base {
	private const COLUMNS = [
		'talk_matrix_accounts' => ['access_token', 'olm_account', 'cross_signing'],
		'talk_matrix_olm_sessions' => ['pickle'],
		'talk_matrix_megolm_in' => ['pickle'],
		'talk_matrix_megolm_out' => ['pickle'],
		'talk_matrix_secrets' => ['value_enc'],
	];

	public function __construct(
		private readonly IDBConnection $db,
		private readonly ICrypto $crypto,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();
		$this
			->setName('talk:matrix:rekey')
			->setDescription('Re-encrypt Matrix tokens and keys after the instance secret changed')
			->addOption('old-secret', null, InputOption::VALUE_REQUIRED, 'The previous value of "secret" from config.php')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report what would be re-encrypted');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$oldSecret = (string)($input->getOption('old-secret') ?? '');
		if ($oldSecret === '') {
			$output->writeln('<error>--old-secret is required</error>');
			return 1;
		}
		$dryRun = (bool)$input->getOption('dry-run');
		$total = 0;
		$failed = 0;
		foreach (self::COLUMNS as $table => $columns) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', ...$columns)->from($table);
			$rows = $qb->executeQuery()->fetchAll();
			foreach ($rows as $row) {
				$update = $this->db->getQueryBuilder();
				$update->update($table)->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
				$changed = false;
				foreach ($columns as $column) {
					$value = $row[$column];
					if ($value === null || $value === '') {
						continue;
					}
					try {
						$plain = $this->crypto->decrypt((string)$value, $oldSecret);
					} catch (\Throwable) {
						try {
							$this->crypto->decrypt((string)$value); // already on the current secret?
							continue;
						} catch (\Throwable) {
							$failed++;
							$output->writeln('<comment>' . $table . '#' . $row['id'] . '.' . $column . ': cannot decrypt with either secret</comment>');
							continue;
						}
					}
					$update->set($column, $update->createNamedParameter($this->crypto->encrypt($plain)));
					$changed = true;
					$total++;
				}
				if ($changed && !$dryRun) {
					$update->executeStatement();
				}
			}
		}
		$output->writeln('<info>' . ($dryRun ? 'Would re-encrypt ' : 'Re-encrypted ') . $total . ' value(s)' . ($failed > 0 ? ', ' . $failed . ' undecryptable' : '') . '</info>');
		return $failed > 0 ? 2 : 0;
	}
}
