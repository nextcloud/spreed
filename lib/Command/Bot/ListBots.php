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
use OCA\Talk\Model\Bot;
use OCA\Talk\Model\BotConversation;
use OCA\Talk\Model\BotConversationMapper;
use OCA\Talk\Model\BotServerMapper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListBots extends Base {
	public function __construct(
		private BotConversationMapper $botConversationMapper,
		private BotServerMapper $botServerMapper,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		parent::configure();
		$this
			->setName('talk:bot:list')
			->setDescription('List all installed bots of the server or a conversation')
			->addArgument(
				'token',
				InputArgument::OPTIONAL,
				'Conversation token to limit the bot list for'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$bots = $this->botServerMapper->getAllBots();
		$token = $input->getArgument('token');

		if ($token) {
			$botIds = array_map(static function (BotConversation $bot): int {
				return $bot->getBotId();
			}, $this->botConversationMapper->findForToken($token));
		}

		$data = [];
		foreach ($bots as $bot) {
			if ($token && !in_array($bot->getId(), $botIds, true)) {
				continue;
			}

			$botData = $bot->jsonSerialize();
			$botData['features'] = Bot::featureFlagsToLabels($botData['features']);

			if (!$output->isVerbose()) {
				unset($botData['url']);
				unset($botData['url_hash']);
				unset($botData['secret']);
				unset($botData['last_error_date']);
				unset($botData['last_error_message']);
			}

			$data[] = $botData;
		}

		$this->writeTableInOutputFormat($input, $output, $data);
		return 0;
	}
}
