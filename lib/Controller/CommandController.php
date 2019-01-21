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

use OCA\Spreed\Model\Command;
use OCA\Spreed\Model\CommandMapper;
use OCP\AppFramework\Db\DoesNotExistException;
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
	public function index(): DataResponse {
		$commands = $this->commandMapper->findAll();

		$result = [];
		foreach ($commands as $command) {
			$result[] = [
				'id' => $command->getId(),
				'name' => $command->getName(),
				'pattern' => $command->getPattern(),
				'script' => $command->getScript(),
				'output' => $command->getOutput(),
			];
		}

		return new DataResponse($result);
	}

	/**
	 * @param string $name
	 * @param string $pattern
	 * @param string $script
	 * @param int $output
	 * @return DataResponse
	 */
	public function create(string $name, string $pattern, string $script, int $output): DataResponse {
		$command = new Command();

		if (!\in_array($output, [Command::OUTPUT_NONE, Command::OUTPUT_USER, Command::OUTPUT_ALL], true)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		// FIXME Validate "bot name"
		// FIXME Validate "pattern"
		// FIXME Validate "script"

		$command->setName($name);
		$command->setName($pattern);
		$command->setName($script);
		$command->setName($output);

		$this->commandMapper->insert($command);

		return new DataResponse([
			'id' => $command->getId(),
			'name' => $command->getName(),
			'pattern' => $command->getPattern(),
			'script' => $command->getScript(),
			'output' => $command->getOutput(),
		]);
	}

	/**
	 * @param int $id
	 * @return DataResponse
	 */
	public function show(int $id): DataResponse {
		try {
			$command = $this->commandMapper->findById($id);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse([
			'id' => $command->getId(),
			'name' => $command->getName(),
			'pattern' => $command->getPattern(),
			'script' => $command->getScript(),
			'output' => $command->getOutput(),
		]);
	}

	/**
	 * @param int $id
	 * @param string $name
	 * @param string $pattern
	 * @param string $script
	 * @param int $output
	 * @return DataResponse
	 */
	public function update(int $id, string $name, string $pattern, string $script, int $output): DataResponse {
		try {
			$command = $this->commandMapper->findById($id);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!\in_array($output, [Command::OUTPUT_NONE, Command::OUTPUT_USER, Command::OUTPUT_ALL], true)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		// FIXME Validate "bot name"
		// FIXME Validate "pattern"
		// FIXME Validate "script"

		$command->setName($name);
		$command->setName($pattern);
		$command->setName($script);
		$command->setName($output);

		$this->commandMapper->update($command);

		return new DataResponse([
			'id' => $command->getId(),
			'name' => $command->getName(),
			'pattern' => $command->getPattern(),
			'script' => $command->getScript(),
			'output' => $command->getOutput(),
		]);
	}

	/**
	 * @param int $id
	 * @return DataResponse
	 */
	public function destroy(int $id): DataResponse {
		try {
			$command = $this->commandMapper->findById($id);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$this->commandMapper->delete($command);

		return new DataResponse();
	}

}
