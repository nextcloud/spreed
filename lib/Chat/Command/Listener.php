<?php

declare(strict_types=1);
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

namespace OCA\Talk\Chat\Command;

use OCA\Talk\Events\BeforeChatMessageSentEvent;
use OCA\Talk\Model\Command;
use OCA\Talk\Participant;
use OCA\Talk\Service\CommandService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {

	public function __construct(
		protected CommandService $commandService,
		protected Executor $executor,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof BeforeChatMessageSentEvent) {
			$this->executeCommand($event);
		}
	}

	public function executeCommand(BeforeChatMessageSentEvent $event): void {
		$participant = $event->getParticipant();
		if (!$participant instanceof Participant) {
			// No commands for bots 🚓
			return;
		}

		$message = $event->getComment();
		if (str_starts_with($message->getMessage(), '//')) {
			return;
		}

		try {
			[$command, $arguments] = $this->getCommand($message->getMessage());
			$command = $this->commandService->resolveAlias($command);
		} catch (DoesNotExistException) {
			return;
		}

		if (!$this->executor->isCommandAvailableForParticipant($command, $participant)) {
			$command = $this->commandService->find('', 'help');
			$arguments = trim($message->getMessage());
		}

		$this->executor->exec($event->getRoom(), $message, $command, $arguments, $participant);
	}

	/**
	 * @param string $message
	 * @return array{0: Command, 1: string}
	 * @throws DoesNotExistException
	 */
	protected function getCommand(string $message): array {
		[$app, $cmd, $arguments] = $this->matchesCommand($message);

		if ($app === '') {
			throw new DoesNotExistException('No command found');
		}

		try {
			return [$this->commandService->find($app, $cmd), trim($arguments)];
		} catch (DoesNotExistException) {
		}

		try {
			return [$this->commandService->find('', $app), trim($cmd . ' ' . $arguments)];
		} catch (DoesNotExistException) {
		}

		return [$this->commandService->find('', 'help'), trim($message)];
	}

	protected function matchesCommand(string $message): array {
		if (!str_starts_with($message, '/')) {
			return ['', '', ''];
		}

		$cmd = explode(' ', substr($message, 1), 3);
		return [
			$cmd[0],
			$cmd[1] ?? '',
			$cmd[2] ?? '',
		];
	}
}
