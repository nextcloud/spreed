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

namespace OCA\Spreed\Chat\Command;


use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Chat\MessageParser;
use OCA\Spreed\Chat\Parser\Command as CommandParser;
use OCA\Spreed\Model\Command;
use OCA\Spreed\Model\Message;
use OCA\Spreed\Participant;
use OCA\Spreed\Service\CommandService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Comments\IComment;
use OCP\IUser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	/** @var EventDispatcherInterface */
	protected $dispatcher;
	/** @var CommandService */
	protected $commandService;
	/** @var Executor */
	protected $executor;

	public function __construct(EventDispatcherInterface $dispatcher,
								CommandService $commandService,
								Executor $executor) {
		$this->dispatcher = $dispatcher;
		$this->commandService = $commandService;
		$this->executor = $executor;
	}

	public static function register(EventDispatcherInterface $dispatcher): void {
		$dispatcher->addListener(ChatManager::class . '::preSendMessage', function(GenericEvent $event) {
			/** @var IComment $message */
			$message = $event->getArgument('comment');
			/** @var Participant $participant */
			$participant = $event->getArgument('participant');

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			if (strpos($message->getMessage(), '//') === 0) {
				return;
			}

			try {
				/** @var Command $command */
				/** @var string $arguments */
				[$command, $arguments] = $listener->getCommand($message->getMessage());
				$command = $listener->commandService->resolveAlias($command);
			} catch (DoesNotExistException $e) {
				return;
			}

			if ($command->getEnabled() === Command::ENABLED_OFF) {
				return;
			}

			if ($command->getEnabled() === Command::ENABLED_MODERATOR && !$participant->hasModeratorPermissions()) {
				return;
			}

			if ($command->getEnabled() === Command::ENABLED_USERS && $participant->isGuest()) {
				return;
			}

			$listener->executor->exec($event->getSubject(), $message, $command, $arguments);
		});
	}

	/**
	 * @param string $message
	 * @return array [Command, string]
	 * @throws DoesNotExistException
	 */
	public function getCommand(string $message): array {
		[$app, $cmd, $arguments] = $this->matchesCommand($message);

		if ($app === '') {
			throw new DoesNotExistException('No command found');
		}

		try {
			return [$this->commandService->find($app, $cmd), trim($arguments)];
		} catch (DoesNotExistException $e) {
		}

		try {
			return [$this->commandService->find('',  $app), trim($cmd . ' ' . $arguments)];
		} catch (DoesNotExistException $e) {
		}

		return [$this->commandService->find('',  'help'), trim($message)];
	}

	protected function matchesCommand(string $message): array {
		if (strpos($message, '/') !== 0) {
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
