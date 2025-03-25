<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Developer;

use OC\Core\Command\Base;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AgeChatMessages extends Base {
	public function __construct(
		private readonly IConfig $config,
		private readonly IDBConnection $connection,
		private readonly Manager $manager,
	) {
		parent::__construct();
	}

	public function isEnabled(): bool {
		return $this->config->getSystemValue('debug', false) === true;
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('talk:developer:age-chat-messages')
			->setDescription('Artificially ages chat messages in the given conversation, so deletion and other things can be tested')
			->addArgument(
				'token',
				InputArgument::REQUIRED,
				'Token of the room to manipulate'
			)
			->addOption(
				'hours',
				null,
				InputOption::VALUE_REQUIRED,
				'Number of hours to age all chat messages',
				24
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$token = $input->getArgument('token');
		$hours = (int)$input->getOption('hours');
		if ($hours < 1) {
			$output->writeln('<error>Invalid age: ' . $hours . '</error>');
			return 1;
		}

		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException) {
			$output->writeln('<error>Room not found: ' . $token . '</error>');
			return 1;
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('comments')
			->set('creation_timestamp', $update->createParameter('creation_timestamp'))
			->set('expire_date', $update->createParameter('expire_date'))
			->set('meta_data', $update->createParameter('meta_data'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		$query = $this->connection->getQueryBuilder();
		$query->select('id', 'creation_timestamp', 'expire_date', 'meta_data')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter('chat')))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter($room->getId())));

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$creationTimestamp = new \DateTime($row['creation_timestamp']);
			$creationTimestamp->sub(new \DateInterval('PT' . $hours . 'H'));

			$expireDate = null;
			if ($row['expire_date']) {
				$expireDate = new \DateTime($row['expire_date']);
				$expireDate->sub(new \DateInterval('PT' . $hours . 'H'));
			}

			$metaData = 'null';
			if ($row['meta_data'] !== 'null') {
				$metaData = json_decode($row['meta_data'], true);
				if (isset($metaData['last_edited_time'])) {
					$metaData['last_edited_time'] -= $hours * 3600;
				}
				$metaData = json_encode($metaData);
			}

			$update->setParameter('id', $row['id']);
			$update->setParameter('creation_timestamp', $creationTimestamp, IQueryBuilder::PARAM_DATE);
			$update->setParameter('expire_date', $expireDate, IQueryBuilder::PARAM_DATE);
			$update->setParameter('meta_data', $metaData);
			$update->executeStatement();
		}
		$result->closeCursor();

		return 0;
	}
}
