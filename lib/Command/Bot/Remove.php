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
