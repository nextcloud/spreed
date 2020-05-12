<?php
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Controller;

use OCA\Talk\Model\Command;
use OCA\Talk\Service\CommandService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class CommandController extends OCSController {

	/** @var CommandService */
	protected $commandService;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param CommandService $commandService
	 */
	public function __construct($appName,
								IRequest $request,
								CommandService $commandService) {
		parent::__construct($appName, $request);
		$this->commandService = $commandService;
	}

	/**
	 * @return DataResponse
	 */
	public function index(): DataResponse {
		$commands = $this->commandService->findAll();

		$result = array_map(function (Command $command) {
			return $command->asArray();
		}, $commands);

		return new DataResponse($result);
	}
}
