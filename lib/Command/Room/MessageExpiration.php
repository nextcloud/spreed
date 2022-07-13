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

use InvalidArgumentException;
use OC\Core\Command\Base;
use OCA\Talk\Exceptions\RoomNotFoundException;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MessageExpiration extends Base {
	use TRoomCommand;

	protected function configure(): void {
		$this
			->setName('talk:room:message-expiration')
			->setDescription('Set message expire duration of a room')
			->addArgument(
				'token',
				InputArgument::REQUIRED,
				'Token of the room to set the message expire duration'
			)->addOption(
				'seconds',
				null,
				InputOption::VALUE_REQUIRED,
				'Seconds to expire a message after sent. If zero will disable the expire message duration.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$token = $input->getArgument('token');
		$seconds = (int) $input->getOption('seconds');

		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException $e) {
			$output->writeln('<error>Room not found.</error>');
			return 1;
		}

		try {
			$this->setMessageExpiration($room, $seconds);
		} catch (InvalidArgumentException $e) {
			$output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
			return 1;
		}

		if ($seconds > 0) {
			$output->writeln('<info>Message expiration enabled successful as ' . $seconds . ' seconds.</info>');
		} else {
			$output->writeln('<info>Message expiration disabled successful.</info>');
		}
		return 0;
	}

	public function completeArgumentValues($argumentName, CompletionContext $context) {
		switch ($argumentName) {
			case 'token':
				return $this->completeTokenValues($context);
		}

		return parent::completeArgumentValues($argumentName, $context);
	}
}
