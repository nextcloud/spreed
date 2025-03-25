<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Monitor;

use OC\Core\Command\Base;
use OCA\Talk\Participant;
use OCP\IDBConnection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HasActiveCalls extends Base {

	public function __construct(
		protected IDBConnection $connection,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();

		$this
			->setName('talk:active-calls')
			->setDescription('Allows you to check if calls are currently in process')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->count('*', 'num_calls'))
			->from('talk_rooms')
			->where($query->expr()->isNotNull('active_since'));

		$result = $query->executeQuery();
		$numCalls = (int)$result->fetchColumn();
		$result->closeCursor();

		if ($numCalls === 0) {
			if ($input->getOption('output') === 'plain') {
				$output->writeln('<info>No calls in progress</info>');
			} else {
				$data = ['calls' => 0, 'participants' => 0];
				$this->writeArrayInOutputFormat($input, $output, $data);
			}
			return 0;
		}

		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->count('*', 'num_participants'))
			->from('talk_sessions')
			->where($query->expr()->gt('in_call', $query->createNamedParameter(Participant::FLAG_DISCONNECTED)))
			->andWhere($query->expr()->gt('last_ping', $query->createNamedParameter(time() - 60)));

		$result = $query->executeQuery();
		$numParticipants = (int)$result->fetchColumn();
		$result->closeCursor();


		if ($input->getOption('output') === 'plain') {
			$output->writeln(sprintf('<error>There are currently %1$d calls in progress with %2$d participants</error>', $numCalls, $numParticipants));
		} else {
			$data = ['calls' => $numCalls, 'participants' => $numParticipants];
			$this->writeArrayInOutputFormat($input, $output, $data);
		}
		return 1;
	}
}
