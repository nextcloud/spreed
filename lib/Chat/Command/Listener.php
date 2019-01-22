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
use OCA\Spreed\Chat\Parser\Command;
use OCA\Spreed\Model\CommandMapper;
use OCA\Spreed\Room;
use OCP\Comments\IComment;
use OCP\IUser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	/** @var EventDispatcherInterface */
	protected $dispatcher;
	/** @var CommandMapper */
	protected $commandMapper;
	/** @var DefaultExecutor */
	protected $defaultExecutor;

	public function __construct(EventDispatcherInterface $dispatcher, CommandMapper $commandMapper, DefaultExecutor $defaultExecutor) {
		$this->dispatcher = $dispatcher;
		$this->commandMapper = $commandMapper;
		$this->defaultExecutor = $defaultExecutor;
	}

	public static function register(EventDispatcherInterface $dispatcher): void {
		$dispatcher->addListener(ChatManager::class . '::preSendMessage', function(GenericEvent $event) {
			/** @var IComment $message */
			$message = $event->getArgument('comment');
			if (strpos($message->getMessage(), '/') === 0) {
				/** @var self $listener */
				$listener = \OC::$server->query(self::class);

				if (!$listener->executeCommands($event)) {
					$listener->showHelp($event);
				}
			}
		});

		$this->dispatcher->addListener(MessageParser::class . '::parseMessage', function(GenericEvent $event) {
			/** @var IComment $chatMessage */
			$chatMessage = $event->getSubject();

			if ($chatMessage->getVerb() !== 'command') {
				return;
			}

			/** @var Command $parser */
			$parser = \OC::$server->query(Command::class);

			$user = $event->getArgument('user');
			if ($user instanceof IUser) {
				$parser->setUser($event->getArgument('user'));
			}

			try {
				[$message, $parameters] = $parser->parseMessage($chatMessage);

				$event->setArguments([
					'message' => $message,
					'parameters' => $parameters,
				]);
				$event->stopPropagation();
			} catch (\OutOfBoundsException $e) {
				// Unknown message, ignore
			} catch (\RuntimeException $e) {
				$event->stopPropagation();
			}
		});
	}

	public function executeCommands(GenericEvent $event): bool {
		/** @var Room $room */
		$room = $event->getSubject();
		/** @var IComment $message */
		$message = $event->getArgument('comment');

		$commands = $this->commandMapper->findAll();
		foreach ($commands as $command) {
			if ($this->matchesCommand($message->getMessage(), $command->getCommand())) {
				$this->defaultExecutor->exec($room, $message, $command);
				return true;
			}
		}

		return false;
	}

	public function showHelp(GenericEvent $event): void {
		// FIXME
	}

	protected function matchesCommand(string $message, string $command): bool {
		$command = '/' . $command;

		if ($message === $command) {
			return true;
		}

		return strpos($message, $command . ' ') === 0;
	}
}
