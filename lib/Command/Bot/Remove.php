<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\Bot;

use OC\Core\Command\Base;
use OCA\Talk\Model\BotConversationMapper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Remove extends Base {
	public function __construct(
		private BotConversationMapper $botConversationMapper,
	) {
		parent::__construct();
	}

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
		$botId = (int) $input->getArgument('bot-id');
		$tokens = $input->getArgument('token');

		$this->botConversationMapper->deleteByBotIdAndTokens($botId, $tokens);

		$output->writeln('<info>Remove bot from given conversations</info>');
		return 0;
	}
}
