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
use OCA\Talk\Model\BotServerMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class State extends Base {
	public function __construct(
		private BotServerMapper $botServerMapper,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		parent::configure();
		$this
			->setName('talk:bot:state')
			->setDescription('Change the state or feature list for a bot')
			->addArgument(
				'bot-id',
				InputArgument::REQUIRED,
				'Bot ID to change the state for'
			)
			->addArgument(
				'state',
				InputArgument::REQUIRED,
				'New state for the bot (0 = disabled, 1 = enabled, 2 = no setup via GUI)'
			)
			->addOption(
				'feature',
				'f',
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Specify the list of features for the bot' . "\n"
				. ' - webhook: The bot receives posted chat messages as webhooks' . "\n"
				. ' - response: The bot can post messages and reactions as a response' . "\n"
				. ' - none: When all features should be disabled for the bot'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$botId = (int)$input->getArgument('bot-id');
		$state = (int)$input->getArgument('state');

		$featureFlags = null;
		if (!empty($input->getOption('feature'))) {
			$featureFlags = Bot::featureLabelsToFlags($input->getOption('feature'));
		}

		if (!in_array($state, [Bot::STATE_DISABLED, Bot::STATE_ENABLED, Bot::STATE_NO_SETUP], true)) {
			$output->writeln('<error>Provided state is invalid</error>');
			return 1;
		}

		try {
			$bot = $this->botServerMapper->findById($botId);
		} catch (DoesNotExistException) {
			$output->writeln('<error>Bot could not be found by id: ' . $botId . '</error>');
			return 1;
		}

		$bot->setState($state);
		if ($featureFlags !== null) {
			$bot->setFeatures($featureFlags);
		}
		$this->botServerMapper->update($bot);

		if ($featureFlags !== null) {
			$output->writeln('<info>Bot state set to ' . $state . ' with features: ' . Bot::featureFlagsToLabels($featureFlags) . '</info>');
		} else {
			$output->writeln('<info>Bot state set to ' . $state . '</info>');
		}
		return 0;
	}
}
