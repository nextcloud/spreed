<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019, Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Migration;

use OCA\Spreed\Model\Command;
use OCA\Spreed\Service\CommandService;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RegisterSampleCommands implements IRepairStep {

	/** @var CommandService */
	protected $service;
	/** @var IAppManager */
	protected $appManager;

	public function __construct(CommandService $service, IAppManager $appManager) {
		$this->service = $service;
		$this->appManager = $appManager;
	}

	public function getName(): string {
		return 'Create help command';
	}

	public function run(IOutput $output): void {
		try {
			$this->service->find('', 'wiki');
		} catch (DoesNotExistException $e) {

			$this->service->create(
				'',
				'wiki',
				'Wikipedia search',
				'php ' . $this->appManager->getAppPath('spreed') . '/sample-commands/wikipedia.php "{ARGUMENTS_DOUBLEQUOTE_ESCAPED}"',
				Command::RESPONSE_ALL,
				Command::ENABLED_ALL
			);
		}
	}
}
