<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Command\Bot;

use OC\Core\Command\Base;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Bot;
use OCA\Talk\Model\BotConversation;
use OCA\Talk\Model\BotConversationMapper;
use OCA\Talk\Model\BotServerMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Setup extends Base {
	public function __construct(
		private Manager $roomManager,
		private BotServerMapper $botServerMapper,
		private BotConversationMapper $botConversationMapper,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		parent::configure();
		$this
			->setName('talk:bot:setup')
			->setDescription('Add a bot to a conversation')
			->addArgument(
				'bot-id',
				InputArgument::REQUIRED,
				'The ID of the bot to set up in a conversation'
			)
			->addArgument(
				'token',
				InputArgument::IS_ARRAY,
				'Conversation tokens to set the bot up for'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$botId = (int) $input->getArgument('bot-id');
		$tokens = $input->getArgument('token');

		try {
			$this->botServerMapper->findById($botId);
		} catch (DoesNotExistException) {
			$output->writeln('<error>Bot could not be found by id: ' . $botId . '</error>');
			return 1;
		}

		$returnCode = 0;
		foreach ($tokens as $token) {
			try {
				$room = $this->roomManager->getRoomByToken($token);

				if ($room->getRemoteServer() !== '') {
					$output->writeln('<error>Federated conversations can not have bots: ' . $token . '</error>');
					$returnCode = 2;
				}
			} catch (RoomNotFoundException) {
				$output->writeln('<error>Conversation could not be found by token: ' . $token . '</error>');
				$returnCode = 2;
			}

			$bot = new BotConversation();
			$bot->setBotId($botId);
			$bot->setToken($token);
			$bot->setState(Bot::STATE_ENABLED);

			try {
				$this->botConversationMapper->insert($bot);
				$output->writeln('<info>Successfully set up for conversation ' . $token . '</info>');
			} catch (\Exception $e) {
				if ($e instanceof Exception && $e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					$output->writeln('<error>Bot is already set up for the conversation ' . $token . '</error>');
					$returnCode = 3;
				} else {
					$output->writeln('<error>' . get_class($e) . ': ' . $e->getMessage() . '</error>');
					$returnCode = 4;
				}
			}
		}

		return $returnCode;
	}
}
