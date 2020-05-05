<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
 *
 * @author Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
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

namespace OCA\Talk\Command\Room;

use OC\Core\Command\Base;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCP\IConfig;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Base {
	/** @var IConfig */
	private $config;

	/** @var Manager */
	public $manager;

	public function __construct(IConfig $config, Manager $manager) {
		parent::__construct();

		$this->config = $config;
		$this->manager = $manager;
	}

	protected function configure(): void {
		parent::configure();

		$this
			->setName('talk:room:list')
			->setDescription('Lists all rooms')
			->addArgument(
				'user',
				InputArgument::REQUIRED,
				'Lists all rooms of the given user'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): ?int {
		$userId = $input->getArgument('user');

		$outputFormat = $input->getOption('output');

		$result = [];
		foreach ($this->manager->getRoomsForParticipant($userId) as $room) {
			if ($room->getType() === Room::ONE_TO_ONE_CALL) {
				continue;
			}

			$public = $room->getType() === Room::PUBLIC_CALL;
			$readOnly = $room->getReadOnly() === Room::READ_ONLY;
			$password = $room->hasPassword();
			$lastActivity = $room->getLastActivity();

			if ($outputFormat === Base::OUTPUT_FORMAT_PLAIN) {
				$public = $public ? 'yes' : 'no';
				$readOnly = $readOnly ? 'yes' : 'no';
				$password = $password ? 'yes' : 'no';
				$lastActivity = $lastActivity ? $lastActivity->format('c') : '';
			} else {
				$lastActivity = $lastActivity ? (int) $lastActivity->format('U') : null;
			}

			$result[] = [
				'token' => $room->getToken(),
				'name' => $room->getName(),
				'public' => $public,
				'readOnly' => $readOnly,
				'password' => $password,
				'users' => $room->getNumberOfParticipants(),
				'moderators' => $room->getNumberOfModerators(),
				'lastActivity' => $lastActivity,
			];
		}

		if ($outputFormat === Base::OUTPUT_FORMAT_PLAIN) {
			(new Table($output))
				->setHeaders(['token', 'name', 'public', 'readOnly', 'password', 'users', 'moderators', 'lastActivity'])
				->addRows($result)
				->render();
		} else {
			$this->writeArrayInOutputFormat($input, $output, $result);
		}

		return 0;
	}
}
