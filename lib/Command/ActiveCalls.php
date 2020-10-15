<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Command;

use OC\Core\Command\Base;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCP\IDBConnection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ActiveCalls extends Base {

	/** @var IDBConnection */
	public $connection;

	/** @var Manager */
	public $manager;

	public function __construct(IDBConnection $connection) {
		parent::__construct();

		$this->connection = $connection;
	}

	protected function configure(): void {
		$this
			->setName('talk:active-calls')
			->setDescription('Allows you to check if calls are currently in process');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->count('*', 'num_calls'))
			->from('talk_rooms')
			->where($query->expr()->isNotNull('active_since'));

		$result = $query->execute();
		$numCalls = (int) $result->fetchColumn();
		$result->closeCursor();

		if ($numCalls === 0) {
			$output->writeln('<info>No calls in progress</info>');
			return 0;
		}

		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->count('*', 'num_participants'))
			->from('talk_sessions')
			->where($query->expr()->gt('in_call', $query->createNamedParameter(Participant::FLAG_DISCONNECTED)))
			->andWhere($query->expr()->gt('last_ping', $query->createNamedParameter(time() - 60)));

		$result = $query->execute();
		$numParticipants = (int) $result->fetchColumn();
		$result->closeCursor();

		$output->writeln(sprintf('<error>There are currently %1$d calls in progress with %2$d participants</error>', $numCalls, $numParticipants));
		return 1;
	}
}
