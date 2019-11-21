<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\Talk\Flow;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager as TalkManager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\WorkflowEngine\IManager as FlowManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use UnexpectedValueException;

class Operation implements IOperation {

	/** @var IL10N */
	private $l;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var TalkManager */
	private $talkManager;
	/** @var IUserSession */
	private $session;
	/** @var ChatManager */
	private $chatManager;

	public function __construct(
		IL10N $l,
		IURLGenerator $urlGenerator,
		TalkManager $talkManager,
		IUserSession $session,
		ChatManager $chatManager
	) {
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
		$this->talkManager = $talkManager;
		$this->session = $session;
		$this->chatManager = $chatManager;
	}

	public static function register(EventDispatcherInterface $dispatcher): void {
		$dispatcher->addListener(FlowManager::EVENT_NAME_REG_OPERATION, function (GenericEvent $event) {
			$operation = \OC::$server->query(Operation::class);
			$event->getSubject()->registerOperation($operation);
		});
	}

	public function getDisplayName(): string {
		return $this->l->t('Write to conversation');
	}

	public function getDescription(): string {
		return $this->l->t('Writes event information into a conversation of your choice');
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('spreed', 'app.svg');
	}

	public function isAvailableForScope(int $scope): bool {
		return $scope === FlowManager::SCOPE_USER;
	}

	/**
	 * Validates whether a configured workflow rule is valid. If it is not,
	 * an `\UnexpectedValueException` is supposed to be thrown.
	 *
	 * @throws UnexpectedValueException
	 * @since 9.1
	 */
	public function validateOperation(string $name, array $checks, string $operation): void {
		list($mode, $token) = $this->parseOperationConfig($operation);
		$this->validateOperationConfig($mode, $token);
	}

	/**
	 * Is being called by the workflow engine when an event was triggered that
	 * is configured for this operation. An evaluation whether the event
	 * qualifies for this operation to run has still to be done by the
	 * implementor by calling the RuleMatchers getMatchingOperations method
	 * and evaluating the results.
	 *
	 * If the implementor is an IComplexOperation, this method will not be
	 * called automatically. It can be used or left as no-op by the implementor.
	 *
	 * @since 18.0.0
	 */
	public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void {
		$flows = $ruleMatcher->getMatchingOperations(self::class, false);
		foreach ($flows as $flow) {
			try {
				list($mode, $token) = $this->parseOperationConfig($flow['operation']);
				$this->validateOperationConfig($mode, $token);
			} catch(UnexpectedValueException $e) {
				continue;
			}

			$room = $this->getRoom($token);
			$participant = $this->getParticipant($room);
			$this->chatManager->sendMessage(
				$room,
				$participant,
				'bots',
				$participant->getUser(),
				'MESSAGE TODO',
				new \DateTime(),
				null
			);
		}
	}

	protected function parseOperationConfig(string $raw): array {
		/**
		 * We expect $operation be a base64 encoded json string, containing
		 * 	't' => string, the room token
		 *  'm' => int 1..3, the mention-mode (none, yourself, room)
		 *  'u' => string, the applicable user id
		 *
		 * setting up room mentions are only permitted to moderators
		 */

		$decoded = base64_decode($raw);
		$opConfig = \json_decode($decoded, true);
		if(!is_array($opConfig) || empty($opConfig)) {
			throw new UnexpectedValueException('Cannot decode operation details');
		}

		$mode = (int)($opConfig['m'] ?? 0);
		$token = trim((string)($opConfig['t'] ?? ''));

		return [$mode, $token];
	}

	protected function validateOperationConfig(int $mode, string $token): void {
		if(($mode < 1 || $mode > 3)) {
			throw new UnexpectedValueException('Invalid mode');
		}

		if(empty($token)) {
			throw new UnexpectedValueException('Invalid token');
		}

		try {
			$room = $this->getRoom($token);
		} catch (RoomNotFoundException $e) {
			throw new UnexpectedValueException('Room not found', $e->getCode(), $e);
		}

		if($mode === 3) {
			try {
				$participant = $this->getParticipant($room);
				if (!$participant->hasModeratorPermissions(false)) {
					throw new UnexpectedValueException('Not allowed to mention room');
				}
			} catch (ParticipantNotFoundException $e) {
				throw new UnexpectedValueException('Participant not found', $e->getCode(), $e);
			}
		}
	}

	/**
	 * @throws UnexpectedValueException
	 */
	protected function getUser(): IUser {
		$user = $this->session->getUser();
		if($user === null) {
			throw new UnexpectedValueException('User not logged in');
		}
		return $user;
	}

	/**
	 * @throws RoomNotFoundException
	 */
	protected function getRoom(string $token): Room {
		$user = $this->getUser();
		return $this->talkManager->getRoomForParticipantByToken($token, $user->getUID());
	}

	/**
	 * @throws ParticipantNotFoundException
	 */
	protected function getParticipant(Room $room): Participant {
		$user = $this->getUser();
		return $room->getParticipant($user->getUID());
	}
}
