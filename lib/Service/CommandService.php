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

namespace OCA\Spreed\Service;


use OCA\Spreed\Model\Command;
use OCA\Spreed\Model\CommandMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class CommandService {

	/** @var CommandMapper */
	protected $mapper;

	public function __construct(CommandMapper $mapper) {
		$this->mapper = $mapper;
	}

	/**
	 * @param string $app
	 * @param string $cmd
	 * @param string $name
	 * @param string $script
	 * @param int $response
	 * @param int $enabled
	 * @return Command
	 * @throws \InvalidArgumentException
	 */
	public function create(string $app, string $cmd, string $name, string $script, int $response, int $enabled): Command {
		try {
			$this->mapper->find($app, $cmd);
			throw new \InvalidArgumentException('command', 1);
		} catch (DoesNotExistException $e) {
		}

		if (!\in_array($response, [Command::RESPONSE_NONE, Command::RESPONSE_USER, Command::RESPONSE_ALL], true)) {
			throw new \InvalidArgumentException('response', 4);
		}

		if (!\in_array($enabled, [Command::ENABLED_OFF, Command::ENABLED_MODERATOR, Command::ENABLED_USERS, Command::ENABLED_ALL], true)) {
			throw new \InvalidArgumentException('enabled', 5);
		}

		$command = new Command();
		$command->setApp($app);
		$command->setCommand($cmd);
		// FIXME Validate "bot name"
		$command->setName($name);
		// FIXME Validate "script"
		$command->setScript($script);
		$command->setResponse($response);
		$command->setEnabled($enabled);

		return $this->mapper->insert($command);
	}

	/**
	 * @param int $id
	 * @param int $response
	 * @param int $enabled
	 * @return Command
	 * @throws \InvalidArgumentException
	 * @throws DoesNotExistException
	 */
	public function updateFromWeb(int $id, int $response, int $enabled): Command {
		$command = $this->mapper->findById($id);
		return $this->update($id, $command->getCommand(), $command->getName(), $command->getScript(), $response, $enabled);
	}

	/**
	 * @param int $id
	 * @param string $cmd
	 * @param string $name
	 * @param string $script
	 * @param int $response
	 * @param int $enabled
	 * @return Command
	 * @throws \InvalidArgumentException
	 * @throws DoesNotExistException
	 */
	public function update(int $id, string $cmd, string $name, string $script, int $response, int $enabled): Command {
		$command = $this->mapper->findById($id);
		if (!\in_array($response, [Command::RESPONSE_NONE, Command::RESPONSE_USER, Command::RESPONSE_ALL], true)) {
			throw new \InvalidArgumentException('response', 4);
		}

		if (!\in_array($enabled, [Command::ENABLED_OFF, Command::ENABLED_MODERATOR, Command::ENABLED_USERS, Command::ENABLED_ALL], true)) {
			throw new \InvalidArgumentException('enabled', 5);
		}

		if ($command->getApp() !== '' || $command->getCommand() === 'help') {
			throw new \InvalidArgumentException('app', 0);
		}

		if ($cmd !== $command->getCommand()) {
			try {
				$this->mapper->find('', $cmd);
				throw new \InvalidArgumentException('command', 1);
			} catch (DoesNotExistException $e) {
				$command->setCommand($cmd);
			}
		}

		// FIXME Validate "bot name"
		$command->setName($name);
		// FIXME Validate "script"
		$command->setScript($script);

		$command->setResponse($response);
		$command->setEnabled($enabled);

		return $this->mapper->update($command);
	}

	/**
	 * @param int $id
	 * @return Command
	 * @throws DoesNotExistException
	 */
	public function delete(int $id): Command {
		$command = $this->mapper->findById($id);
		return $this->mapper->delete($command);
	}
	/**
	 * @param string $app
	 * @param string $cmd
	 * @return Command
	 * @throws DoesNotExistException
	 */
	public function find(string $app, string $cmd): Command {
		return $this->mapper->find($app, $cmd);
	}

	/**
	 * @param int $id
	 * @return Command
	 * @throws DoesNotExistException
	 */
	public function findById(int $id): Command {
		return $this->mapper->findById($id);
	}

	/**
	 * @return Command[]
	 */
	public function findAll(): array {
		return $this->mapper->findAll();
	}
}
