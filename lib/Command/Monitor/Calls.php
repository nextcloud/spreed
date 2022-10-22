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

namespace OCA\Talk\Command\Monitor;

use OC\Core\Command\Base;
use OCA\Talk\Participant;
use OCP\IDBConnection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Calls extends Base {
	protected IDBConnection $connection;

	public function __construct(IDBConnection $connection) {
		parent::__construct();

		$this->connection = $connection;
	}

	protected function configure(): void {
		parent::configure();

		$this
			->setName('talk:monitor:calls')
			->setDescription('Prints a list with conversations that have an active call as well as their participant count')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$query = $this->connection->getQueryBuilder();
		$subQuery = $this->connection->getQueryBuilder();
		$subQuery->select('attendee_id')
			->from('talk_sessions')
			->where($subQuery->expr()->gt('in_call', $query->createNamedParameter(Participant::FLAG_DISCONNECTED)))
			->andWhere($subQuery->expr()->gt('last_ping', $query->createNamedParameter(time() - 60)))
			->groupBy('attendee_id');

		$query->select('r.token', $query->func()->count('*', 'num_attendees'))
			->from('talk_attendees', 'a')
			->leftJoin('a', 'talk_rooms', 'r', $query->expr()->eq('a.room_id', 'r.id'))
			->where($query->expr()->in('a.id', $query->createFunction($subQuery->getSQL())))
			->groupBy('r.token');

		$data = [];
		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$key = (string) $row['token'];
			if ($input->getOption('output') === Base::OUTPUT_FORMAT_PLAIN) {
				$key = '"' . $key . '"';
			}

			$data[$key] = (int) $row['num_attendees'];
		}
		$result->closeCursor();

		if ($input->getOption('output') === Base::OUTPUT_FORMAT_PLAIN) {
			$numCalls = count($data);
			$numParticipants = array_sum($data);

			if (empty($data)) {
				$output->writeln('<info>No calls in progress</info>');
			} else {
				$output->writeln(sprintf('<error>There are currently %1$d calls in progress with %2$d participants</error>', $numCalls, $numParticipants));
			}
		}

		$this->writeArrayInOutputFormat($input, $output, $data);
		return 0;
	}
}
