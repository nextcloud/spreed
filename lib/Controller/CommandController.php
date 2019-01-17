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

namespace OCA\Spreed\Controller;

use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Model\CommandMapper;
use OCA\Spreed\Participant;
use OCA\Spreed\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class CommandController extends OCSController {
	/** @var CommandMapper */
	protected $commandMapper;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param CommandMapper $commandMapper
	 */
	public function __construct($appName,
								IRequest $request,
								CommandMapper $commandMapper) {
		parent::__construct($appName, $request);
		$this->commandMapper = $commandMapper;
	}

	/**
	 * @return DataResponse
	 */
	public function getAll(): DataResponse {
		$commands = $this->commandMapper->findAll();

		$result = [];
		foreach ($commands as $command) {
			$result[] = [
				'id' => $command->getId(),
				'pattern' => $command->getPattern(),
				'script' => $command->getScript(),
				'output' => $command->getOutput(),
			];
		}

		return new DataResponse($result);
	}

}
