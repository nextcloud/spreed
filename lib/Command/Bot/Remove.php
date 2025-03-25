<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Bot;

use OC\Core\Command\Base;
use OCA\Talk\Events\BotDisabledEvent;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\BotConversationMapper;
use OCA\Talk\Model\BotServerMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Remove extends Base {
	public function __construct(
		private BotConversationMapper $botConversationMapper,
		private BotServerMapper $botServerMapper,
		private IEventDispatcher $dispatcher,
		private Manager $roomManager,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		parent::configure();
		$this
			->setName('talk:bot:remove')
			->setDescription('Remove a bot from a conversation')
			->addArgument(
				'bot-id',
				InputArgument::REQUIRED,
				'The ID of the bot to remove in a conversation'
			)
			->addArgument(
				'token',
				InputArgument::IS_ARRAY,
				'Conversation tokens to remove bot up for'
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

		$this->botConversationMapper->deleteByBotIdAndTokens($botId, $tokens);
		$output->writeln('<info>Remove bot from given conversations</info>');

		foreach ($tokens as $token) {
			try {
				$room = $this->roomManager->getRoomByToken($token);
			} catch (RoomNotFoundException) {
				continue;
			}
			$event = new BotDisabledEvent($room, $botServer);
			$this->dispatcher->dispatchTyped($event);
		}

		return 0;
	}
}
