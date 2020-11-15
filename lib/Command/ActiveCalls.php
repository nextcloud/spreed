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
use OCP\IUserManager;
use OCA\Talk\GuestManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ActiveCalls extends Base {

	/** @var IDBConnection */
	public $connection;

	/** @var Manager */
	public $manager;

    /** @var IUserManager */
    protected $userManager;

    /** @var GuestManager */
    protected $guestManager;

	public function __construct(IDBConnection $connection, Manager $manager, IUserManager $userManager, GuestManager $guestManager) {
		parent::__construct();

		$this->connection = $connection;
        $this->manager = $manager;
        $this->userManager = $userManager;
        $this->guestManager = $guestManager;
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

        if ($input->getOption('verbose')) {
                $query = $this->connection->getQueryBuilder();
                $query->select(['id', 'name', 'token', 'active_since'])
                      ->from('talk_rooms')
                      ->where($query->expr()->isNotNull('active_since'));
                $result = $query->execute();
                while ($row = $result->fetch()) {
                    $room = $this->manager->getRoomById(intval($row['id']));
                    $parts = $room->getParticipants();
                    $output->writeln('<info>active call in room: token="' . $row['token'] . '" name="' . $row['name'] . '" since="' . $row['active_since'] . '"</info>');
                    $output->writeln('<info>  active participants:</info>');
                    foreach ($parts as $part) {
                        if ($part->getInCallFlags() == FLAG_DISCONNECTED)
                            continue;

                        if ($part->isGuest()) {
                            $name = $this->guestManager->getNameBySessionHash(
                                sha1($part->getSessionId()));
                            $output->writeln('<info>    guest name="' . $name .
                                             '"</info>');
                        } else {
                            $user = $this->userManager->get($part->getUser());
                            $output->writeln('<info>    id="' . $part->getUser() . '" name="' . $user->getDisplayName() . '"</info>');
                        }
                    }
                }
                $result->closeCursor();
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
