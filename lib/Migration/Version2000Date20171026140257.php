<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Talk\Migration;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\Security\ISecureRandom;

class Version2000Date20171026140257 extends SimpleMigrationStep {

	/** @var IDBConnection */
	protected $connection;

	/** @var IConfig */
	protected $config;

	/** @var ISecureRandom */
	protected $secureRandom;

	/** @var string[] */
	protected $tokens;

	public function __construct(IDBConnection $connection,
								IConfig $config,
								ISecureRandom $secureRandom) {
		$this->connection = $connection;
		$this->config = $config;
		$this->secureRandom = $secureRandom;
		$this->tokens = [];
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @since 13.0.0
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		if (version_compare($this->config->getAppValue('spreed', 'installed_version', '0.0.0'), '2.0.0', '<')) {
			// Migrations only work after 2.0.0
			return;
		}

		$chars = str_replace(['l', '0', '1'], '', ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS);
		$entropy = (int) $this->config->getAppValue('spreed', 'token_entropy', 8);

		$update = $this->connection->getQueryBuilder();
		$update->update('spreedme_rooms')
			->set('token', $update->createParameter('token'))
			->where($update->expr()->eq('id', $update->createParameter('room_id')));

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms')
			->where($query->expr()->emptyString('token'))
			->orWhere($query->expr()->isNull('token'));
		$result = $query->execute();

		$output->startProgress();
		while ($row = $result->fetch()) {
			$output->advance();

			$token = $this->getNewToken($entropy, $chars);

			$update->setParameter('token', $token)
				->setParameter('room_id', (int) $row['id'], IQueryBuilder::PARAM_INT)
				->execute();
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
