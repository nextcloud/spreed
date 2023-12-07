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

namespace OCA\Talk\Chat\Command;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\CommandEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Command;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\CommandService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Comments\IComment;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class Executor {
	public const EVENT_APP_EXECUTE = self::class . '::execApp';

	public const PLACEHOLDER_ROOM = '{ROOM}';
	public const PLACEHOLDER_USER = '{USER}';
	public const PLACEHOLDER_ARGUMENTS = '{ARGUMENTS}';
	public const PLACEHOLDER_ARGUMENTS_DOUBLEQUOTE_ESCAPED = '{ARGUMENTS_DOUBLEQUOTE_ESCAPED}';

	protected IEventDispatcher $dispatcher;

	protected ShellExecutor $shellExecutor;

	protected CommandService $commandService;

	protected LoggerInterface $logger;

	protected IL10N $l;

	public function __construct(
		IEventDispatcher $dispatcher,
		ShellExecutor $shellExecutor,
		CommandService $commandService,
		LoggerInterface $logger,
		IL10N $l,
	) {
		$this->dispatcher = $dispatcher;
		$this->shellExecutor = $shellExecutor;
		$this->commandService = $commandService;
		$this->logger = $logger;
		$this->l = $l;
	}

	public function isCommandAvailableForParticipant(Command $command, Participant $participant): bool {
		if ($command->getEnabled() === Command::ENABLED_OFF) {
			return false;
		}

		if ($command->getEnabled() === Command::ENABLED_MODERATOR && !$participant->hasModeratorPermissions()) {
			return false;
		}

		if ($command->getEnabled() === Command::ENABLED_USERS && $participant->isGuest()) {
			return false;
		}

		return true;
	}

	public function exec(Room $room, IComment $message, Command $command, string $arguments, Participant $participant): void {
		try {
			$command = $this->commandService->resolveAlias($command);
		} catch (DoesNotExistException $e) {
			$user = $message->getActorType() === Attendee::ACTOR_USERS ? $message->getActorId() : '';
			$message->setMessage(json_encode([
				'user' => $user,
				'visibility' => $command->getResponse(),
				'output' => $e->getMessage(),
			]), ChatManager::MAX_CHAT_LENGTH);
			$message->setActor('bots', $command->getName());
			$message->setVerb(ChatManager::VERB_COMMAND);
			return;
		}

		if (($command->getApp() === '' || $command->getApp() === null) && $command->getCommand() === 'help') {
			$output = $this->execHelp($room, $message, $arguments, $participant);
		} elseif ($command->getApp() !== '' && $command->getApp() !== null) {
			$output = $this->execApp($room, $message, $command, $arguments);
		} else {
			$output = $this->execShell($room, $message, $command, $arguments);
		}

		$user = $message->getActorType() === Attendee::ACTOR_USERS ? $message->getActorId() : '';
		$message->setMessage(json_encode([
			'user' => $user,
			'visibility' => $command->getResponse(),
			'output' => $output,
		]), ChatManager::MAX_CHAT_LENGTH);
		$message->setActor('bots', $command->getName());
		$message->setVerb(ChatManager::VERB_COMMAND);
	}

	protected function execHelp(Room $room, IComment $message, string $arguments, Participant $participant): string {
		if ($arguments !== '' && $arguments !== 'help') {
			return $this->execHelpSingleCommand($room, $message, $arguments);
		}

		$helps = [];
		$commands = $this->commandService->findAll();

		foreach ($commands as $command) {
			if ($command->getApp() !== '' && $command->getApp() !== null) {
				$response = $this->execHelpSingleCommand($room, $message, $command->getApp() . ' ' . $command->getCommand());
			} else {
				if ($command->getCommand() === 'help' || strpos($command->getScript(), 'alias:') !== false ||
						!$this->isCommandAvailableForParticipant($command, $participant)) {
					continue;
				}
				$response = $this->execHelpSingleCommand($room, $message, $command->getCommand());
			}

			$response = trim($response);
			$newLinePosition = strpos($response, "\n");
			if ($newLinePosition !== false) {
				$tempHelp = substr($response, 0, $newLinePosition);
				if ($tempHelp === 'Description:') {
					$hasHelpSection = strpos($response, "\nHelp:\n");
					if ($hasHelpSection !== false) {
						// Symfony console command with --help detected
						$tempHelp = substr($response, $hasHelpSection + 7);
						$tempHelp = substr($tempHelp, 0, strpos($tempHelp, "\n"));
					}
				}
				$helps[] = $tempHelp;
			} else {
				$helps[] = $response;
			}
		}

		if (empty($helps)) {
			return $this->l->t('There are currently no commands available.');
		}

		// FIXME Implement a useful help
		return implode("\n", $helps);
	}

	protected function execHelpSingleCommand(Room $room, IComment $message, string $arguments): string {
		try {
			$input = explode(' ', $arguments, 2);
			if (count($input) === 1) {
				$command = $this->commandService->find('', $arguments);
				$response = $this->execShell($room, $message, $command, '--help');

				if (strpos($response, 'Description:') === 0) {
					$hasHelpSection = strpos($response, "\nHelp:\n");
					if ($hasHelpSection !== false) {
						// Symfony console command with --help detected
						$response = substr($response, $hasHelpSection + 7);
					}
				}

				return $response;
			}

			[$app, $cmd] = $input;
			$command = $this->commandService->find($app, $cmd);
			return $this->execApp($room, $message, $command, '--help');
		} catch (DoesNotExistException $e) {
			return $this->l->t('The command does not exist');
		}
	}

	protected function execApp(Room $room, IComment $message, Command $command, string $arguments): string {
		$event = $this->createEvent($room, $message, $command, $arguments);
		$this->dispatcher->dispatch(self::EVENT_APP_EXECUTE, $event);
		return $event->getOutput();
	}

	protected function createEvent(Room $room, IComment $message, Command $command, string $arguments): CommandEvent {
		return new CommandEvent($room, $message, $command, $arguments);
	}

	public function execShell(Room $room, IComment $message, Command $command, string $arguments): string {
		try {
			return $this->shellExecutor->execShell(
				$command->getScript(),
				$arguments,
				$room->getToken(),
				$message->getActorType() === Attendee::ACTOR_USERS ? $message->getActorId() : ''
			);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return $this->l->t('An error occurred while running the command. Please ask an administrator to check the logs.');
		}
	}
}
