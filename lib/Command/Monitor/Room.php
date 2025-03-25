<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Monitor;

use OC\Core\Command\Base;
use OCA\Talk\Participant;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Room extends Base {

	public function __construct(
		protected IDBConnection $connection,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();

		$this
			->setName('talk:monitor:room')
			->setDescription('Prints the number of attendees, active sessions and participant in the call.')
			->addArgument(
				'token',
				InputArgument::REQUIRED,
				'Token of the room to monitor'
			)
			->addOption(
				'separator',
				null,
				InputOption::VALUE_REQUIRED,
				'Separator for the CSV list when output=csv is used',
				','
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$token = $input->getArgument('token');

		$query = $this->connection->getQueryBuilder();
		$query->select('id')
			->from('talk_rooms')
			->where($query->expr()->eq('token', $query->createNamedParameter($token)));

		$result = $query->executeQuery();
		$roomId = (int)$result->fetchOne();
		$result->closeCursor();

		if ($roomId === 0) {
			if ($input->getOption('output') === Base::OUTPUT_FORMAT_PLAIN) {
				$output->writeln(sprintf('<error>Room with token %1$s not found</error>', $token));
			}
			return 1;
		}

		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->count('*', 'num_attendees'))
			->from('talk_attendees')
			->where($query->expr()->eq('room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$numAttendees = (int)$result->fetchOne();
		$result->closeCursor();

		$numSessions = $numSessionsInCall = 0;
		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->count('s.id', 'num_sessions'))
			->from('talk_sessions', 's')
			->leftJoin('s', 'talk_attendees', 'a', $query->expr()->eq('a.id', 's.attendee_id'))
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->gt('s.last_ping', $query->createNamedParameter(time() - 60, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$numSessions = (int)$result->fetchOne();
		$result->closeCursor();

		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->count('s.id', 'num_sessions'))
			->from('talk_sessions', 's')
			->leftJoin('s', 'talk_attendees', 'a', $query->expr()->eq('a.id', 's.attendee_id'))
			->where($query->expr()->eq('a.room_id', $query->createNamedParameter($roomId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->gt('s.in_call', $query->createNamedParameter(Participant::FLAG_DISCONNECTED, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->gt('s.last_ping', $query->createNamedParameter(time() - 60, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$numSessionsInCall = (int)$result->fetchOne();
		$result->closeCursor();

		if ($input->getOption('output') === Base::OUTPUT_FORMAT_PLAIN) {
			$output->writeln(sprintf(
				'The conversation has %1$d attendees with %2$d sessions of which %3$d are in the call.',
				$numAttendees,
				$numSessions,
				$numSessionsInCall
			));
			return 0;
		}
		if ($input->getOption('output') === 'csv') {
			$separator = $input->getOption('separator');
			$output->writeln($numAttendees . $separator . $numSessions . $separator . $numSessionsInCall);
			return 0;
		}

		$this->writeArrayInOutputFormat($input, $output, [
			'attendees' => $numAttendees,
			'sessions' => $numSessions,
			'call' => $numSessionsInCall,
		]);
		return 0;
	}
}
