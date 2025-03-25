<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Migration;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Security\ISecureRandom;

class Version2000Date20171026140257 extends SimpleMigrationStep {

	/** @var string[] */
	protected array $tokens;

	public function __construct(
		protected IDBConnection $connection,
		protected IConfig $config,
		protected ISecureRandom $secureRandom,
	) {
		$this->tokens = [];
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @since 13.0.0
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		if (version_compare($this->config->getAppValue('spreed', 'installed_version', '0.0.0'), '2.0.0', '<')) {
			// Migrations only work after 2.0.0
			return;
		}

		$chars = str_replace(['l', '0', '1'], '', ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS);
		$entropy = (int)$this->config->getAppValue('spreed', 'token_entropy', '8');

		$update = $this->connection->getQueryBuilder();
		$update->update('spreedme_rooms')
			->set('token', $update->createParameter('token'))
			->where($update->expr()->eq('id', $update->createParameter('room_id')));

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms')
			->where($query->expr()->emptyString('token'))
			->orWhere($query->expr()->isNull('token'));
		$result = $query->executeQuery();

		$output->startProgress();
		while ($row = $result->fetch()) {
			$output->advance();

			$token = $this->getNewToken($entropy, $chars);

			$update->setParameter('token', $token)
				->setParameter('room_id', (int)$row['id'], IQueryBuilder::PARAM_INT)
				->executeStatement();
		}
		$output->finishProgress();
	}

	/**
	 * @param int $entropy
	 * @param string $chars
	 * @return string
	 */
	protected function getNewToken(int $entropy, string $chars): string {
		$token = $this->secureRandom->generate($entropy, $chars);
		while (isset($this->tokens[$token])) {
			$token = $this->secureRandom->generate($entropy, $chars);
		}
		$this->tokens[$token] = $token;
		return $token;
	}
}
