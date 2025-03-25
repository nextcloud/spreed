<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Bot;

use OC\Core\Command\Base;
use OCA\Talk\Events\BotEnabledEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Bot;
use OCA\Talk\Model\BotConversation;
use OCA\Talk\Model\BotConversationMapper;
use OCA\Talk\Model\BotServerMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Setup extends Base {
	public function __construct(
		private Manager $roomManager,
		private BotServerMapper $botServerMapper,
		private BotConversationMapper $botConversationMapper,
		private IEventDispatcher $dispatcher,
	) {
		parent::__construct();
	}

	#[\Override]
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
		$botId = (int)$input->getArgument('bot-id');
		$tokens = $input->getArgument('token');

		try {
			$botServer = $this->botServerMapper->findById($botId);
		} catch (DoesNotExistException) {
			$output->writeln('<error>Bot could not be found by id: ' . $botId . '</error>');
			return 1;
		}

		$returnCode = 0;
		foreach ($tokens as $token) {
			try {
				$room = $this->roomManager->getRoomByToken($token);

				if ($room->isFederatedConversation()) {
					$output->writeln('<error>Federated conversations can not have bots: ' . $token . '</error>');
					$returnCode = 2;
					continue;
				}
			} catch (RoomNotFoundException) {
				$output->writeln('<error>Conversation could not be found by token: ' . $token . '</error>');
				$returnCode = 2;
				continue;
			}

			$bot = new BotConversation();
			$bot->setBotId($botId);
			$bot->setToken($token);
			$bot->setState(Bot::STATE_ENABLED);

			try {
				$this->botConversationMapper->insert($bot);
				$output->writeln('<info>Successfully set up for conversation ' . $token . '</info>');

				$event = new BotEnabledEvent($room, $botServer);
				$this->dispatcher->dispatchTyped($event);
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
