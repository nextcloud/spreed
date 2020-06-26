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

namespace OCA\Talk\Command\Command;

use OCA\Talk\Service\CommandService;
use OC\Core\Command\Base;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Delete extends Base {

	/** @var CommandService */
	private $service;

	public function __construct(CommandService $service) {
		parent::__construct();
		$this->service = $service;
	}

	protected function configure():void {
		$this
			->setName('talk:command:delete')
			->setDescription('Remove an existing command')
			->addArgument(
				'command-id',
				InputArgument::REQUIRED
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = (int) $input->getArgument('command-id');

		try {
			$this->service->delete($id);
		} catch (DoesNotExistException $e) {
			$output->writeln('<error>The given command ID does not exist</error>');
			return 1;
		} catch (\InvalidArgumentException $e) {
			$output->writeln('<error>The help command cannot be deleted</error>');
			return 2;
		}
		return 0;
	}
}
