<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Flow;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager as TalkManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\WorkflowEngine\EntityContext\IDisplayText;
use OCP\WorkflowEngine\EntityContext\IUrl;
use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IManager as FlowManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;
use UnexpectedValueException;

class Operation implements IOperation {
	/** @var int[] */
	public const MESSAGE_MODES = [
		'NO_MENTION' => 1,
		'SELF_MENTION' => 2,
		'ROOM_MENTION' => 3,
	];

	public function __construct(
		protected IL10N $l,
		protected IURLGenerator $urlGenerator,
		protected TalkManager $talkManager,
		protected ParticipantService $participantService,
		protected IUserSession $session,
		protected ChatManager $chatManager,
	) {
	}

	#[\Override]
	public function getDisplayName(): string {
		return $this->l->t('Write to conversation');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l->t('Writes event information into a conversation of your choice');
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('spreed', 'app.svg');
	}

	#[\Override]
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
	#[\Override]
	public function validateOperation(string $name, array $checks, string $operation): void {
		[$mode, $token] = $this->parseOperationConfig($operation);
		$this->validateOperationConfig($mode, $token, $this->getUser()->getUID());
	}

	#[\Override]
	public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void {
		$flows = $ruleMatcher->getFlows(false);
		foreach ($flows as $flow) {
			try {
				[$mode, $token] = $this->parseOperationConfig($flow['operation']);
				$uid = $flow['scope_actor_id'];
				$this->validateOperationConfig($mode, $token, $uid);

				$entity = $ruleMatcher->getEntity();

				$message = $this->prepareText($entity, $eventName);
				if ($message === '') {
					continue;
				}

				$room = $this->getRoom($token, $uid);
				if ($room->getReadOnly() !== Room::READ_WRITE) {
					// Ignore conversation because it is locked
					continue;
				}

				if ($room->isFederatedConversation()) {
					// Ignore conversation because it is a proxy conversation
					continue;
				}

				$participant = $this->participantService->getParticipant($room, $uid, false);
				if (!($participant->getPermissions() & Attendee::PERMISSIONS_CHAT)) {
					// Ignore conversation because the user has no permissions
					continue;
				}

				$this->chatManager->sendMessage(
					$room,
					null,
					Attendee::ACTOR_BOTS,
					$participant->getAttendee()->getActorId(),
					$this->prepareMention($mode, $participant) . $message,
					new \DateTime(),
					rateLimitGuestMentions: false,
				);
			} catch (UnexpectedValueException|ParticipantNotFoundException|RoomNotFoundException) {
				continue;
			}
		}
	}

	protected function prepareText(IEntity $entity, string $eventName): string {
		$message = $eventName;
		if ($entity instanceof IDisplayText) {
			$message = trim($entity->getDisplayText(3));
		}
		if ($entity instanceof IUrl && $message !== '') {
			$message .= ' ' . $entity->getUrl();
		}
		return $message;
	}

	/**
	 * returns a mention including a trailing whitespace, or an empty string
	 */
	protected function prepareMention(int $mode, Participant $participant): string {
		switch ($mode) {
			case self::MESSAGE_MODES['ROOM_MENTION']:
				return '@all ';
			case self::MESSAGE_MODES['SELF_MENTION']:
				$hasWhitespace = str_contains($participant->getAttendee()->getActorId(), ' ');
				$enclosure = $hasWhitespace ? '"' : '';
				return '@' . $enclosure . $participant->getAttendee()->getActorId() . $enclosure . ' ';
			case self::MESSAGE_MODES['NO_MENTION']:
			default:
				return '';
		}
	}

	protected function parseOperationConfig(string $raw): array {
		/**
		 * We expect $operation be a json string, containing
		 * 	't' => string, the room token
		 *  'm' => int > 0, see self::MESSAGE_MODES
		 *
		 * setting up room mentions are only permitted to moderators
		 */

		$opConfig = \json_decode($raw, true);
		if (!is_array($opConfig) || empty($opConfig)) {
			throw new UnexpectedValueException('Cannot decode operation details');
		}

		$mode = (int)($opConfig['m'] ?? 0);
		$token = trim((string)($opConfig['t'] ?? ''));

		return [$mode, $token];
	}

	protected function validateOperationConfig(int $mode, string $token, string $uid): void {
		if (!in_array($mode, self::MESSAGE_MODES)) {
			throw new UnexpectedValueException('Invalid mode');
		}

		if (empty($token)) {
			throw new UnexpectedValueException('Invalid token');
		}

		try {
			$room = $this->getRoom($token, $uid);
		} catch (RoomNotFoundException $e) {
			throw new UnexpectedValueException('Room not found', $e->getCode(), $e);
		}

		if ($room->isFederatedConversation()) {
			throw new UnexpectedValueException('Room is a proxy conversation');
		}

		if ($mode === self::MESSAGE_MODES['ROOM_MENTION']) {
			try {
				$participant = $this->participantService->getParticipant($room, $uid, false);
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
		if ($user === null) {
			throw new UnexpectedValueException('User not logged in');
		}
		return $user;
	}

	/**
	 * @throws RoomNotFoundException
	 */
	protected function getRoom(string $token, string $uid): Room {
		return $this->talkManager->getRoomForUserByToken($token, $uid);
	}
}
