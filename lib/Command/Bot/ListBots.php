<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

	#[\Override]
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
