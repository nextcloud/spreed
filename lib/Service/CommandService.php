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

namespace OCA\Talk\Service;

use OCA\Talk\Chat\Command\ShellExecutor;
use OCA\Talk\Model\Command;
use OCA\Talk\Model\CommandMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class CommandService {

	public function __construct(
		protected CommandMapper $mapper,
		protected ShellExecutor $shellExecutor,
	) {
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

		$command = new Command();
		$command->setApp($app);
		$command->setCommand($cmd);
		$command->setName($name);
		$command->setScript($script);
		$command->setResponse($response);
		$command->setEnabled($enabled);

		$this->validateCommand($command);

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

		$command->setName($name);
		$command->setScript($script);
		$command->setResponse($response);
		$command->setEnabled($enabled);

		if ($cmd !== $command->getCommand()) {
			try {
				$this->mapper->find('', $cmd);
				throw new \InvalidArgumentException('command', 1);
			} catch (DoesNotExistException $e) {
				$command->setCommand($cmd);
			}
		}

		$this->validateCommand($command);

		return $this->mapper->update($command);
	}

	/**
	 * @param Command $command
	 * @throws \InvalidArgumentException
	 */
	protected function validateCommand(Command $command): void {
		if (preg_match('/^[a-z0-9]{1..64}$/', $command->getCommand())) {
			throw new \InvalidArgumentException('command', 1);
		}

		if (preg_match('/^.{1..64}$/', $command->getName())) {
			throw new \InvalidArgumentException('name', 2);
		}

		if ($command->getApp() === '' || $command->getApp() === null) {
			$script = $command->getScript();
			if (str_starts_with($script, 'alias:')) {
				try {
					$this->resolveAlias($command);
				} catch (DoesNotExistException $e) {
					throw new \InvalidArgumentException('script', 3);
				}
			} elseif ($script !== 'help') {
				if (preg_match('/[`\'"]{(?:ARGUMENTS|ROOM|USER)}[`\'"]/i', $script)) {
					throw new \InvalidArgumentException('script-parameters', 6);
				}
				if (str_contains($script, '{ARGUMENTS_DOUBLEQUOTE_ESCAPED}')) {
					throw new \InvalidArgumentException('script-parameters', 6);
				}

				try {
					$this->shellExecutor->execShell($script, '--help');
				} catch (\InvalidArgumentException $e) {
					throw new \InvalidArgumentException('script', 3);
				}
			}
		}

		if (!\in_array($command->getResponse(), [Command::RESPONSE_NONE, Command::RESPONSE_USER, Command::RESPONSE_ALL], true)) {
			throw new \InvalidArgumentException('response', 4);
		}

		if (!\in_array($command->getEnabled(), [Command::ENABLED_OFF, Command::ENABLED_MODERATOR, Command::ENABLED_USERS, Command::ENABLED_ALL], true)) {
			throw new \InvalidArgumentException('enabled', 5);
		}
	}

	/**
	 * @param Command $command
	 * @return Command
	 * @throws DoesNotExistException
	 */
	public function resolveAlias(Command $command): Command {
		$script = $command->getScript();
		if (str_starts_with($script, 'alias:')) {
			$alias = explode(':', $script, 3);
			if (isset($alias[2])) {
				[, $app, $cmd] = $alias;
			} else {
				$app = '';
				$cmd = $alias[1];
			}

			if ($app === $command->getApp() && $cmd === $command->getCommand()) {
				throw new DoesNotExistException('The command is an alias for itself');
			}

			try {
				return $this->find($app, $cmd);
			} catch (DoesNotExistException $e) {
				throw new DoesNotExistException('The command for ' . $command->getCommand() . ' does not exist');
			}
		}

		return $command;
	}

	/**
	 * @param int $id
	 * @return Command
	 * @throws DoesNotExistException
	 * @throws \InvalidArgumentException
	 */
	public function delete(int $id): Command {
		$command = $this->mapper->findById($id);

		if (($command->getApp() !== '' && $command->getApp() !== null) || $command->getCommand() === 'help') {
			throw new \InvalidArgumentException('app', 0);
		}

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
	 * @param string $app
	 * @return Command[]
	 */
	public function findByApp(string $app): array {
		return $this->mapper->findByApp($app);
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
