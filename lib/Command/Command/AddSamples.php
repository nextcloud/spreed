<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Command\Command;

use OCA\Spreed\Model\Command;
use OCA\Spreed\Service\CommandService;
use OC\Core\Command\Base;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddSamples extends Base {

	use TRenderCommand;

	/** @var CommandService */
	private $service;
	/** @var IAppManager */
	protected $appManager;

	public function __construct(CommandService $service, IAppManager $appManager) {
		parent::__construct();
		$this->service = $service;
		$this->appManager = $appManager;
	}

	protected function configure(): void {
		$this
			->setName('talk:command:add-samples')
			->setDescription('Adds some sample commands: /wiki, …')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		try {
			$appPath = $this->appManager->getAppPath('spreed');
		} catch (AppPathNotFoundException $e) {
			$output->writeln('<error>Could not determine the spreed/ app directory.</error>');
			return 1;
		}

		$commands = [];
		try {
			$this->service->find('', 'wiki');
		} catch (DoesNotExistException $e) {
			$commands[] = $this->service->create(
				'',
				'wiki',
				'Wikipedia',
				'php ' . $appPath . '/sample-commands/wikipedia.php "{ARGUMENTS_DOUBLEQUOTE_ESCAPED}"',
				Command::RESPONSE_ALL,
				Command::ENABLED_ALL
			);
		}

		try {
			$this->service->find('', 'hackernews');
		} catch (DoesNotExistException $e) {
			$commands[] = $this->service->create(
				'',
				'hackernews',
				'Hacker News',
				'php ' . $appPath . '/sample-commands/hackernews.php "{ARGUMENTS_DOUBLEQUOTE_ESCAPED}"',
				Command::RESPONSE_ALL,
				Command::ENABLED_ALL
			);
		}

		$output->writeln('<info>Commands added</info>');
		$output->writeln('');
		$this->renderCommands(Base::OUTPUT_FORMAT_PLAIN, $output, $commands);
	}
}
