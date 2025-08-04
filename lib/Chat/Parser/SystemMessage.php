<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat\Parser;

use OCA\Circles\CirclesManager;
use OCA\DAV\CardDAV\PhotoCache;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\MessageParseEvent;
use OCA\Talk\Events\OverwritePublicSharePropertiesEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Federation\Authenticator;
use OCA\Talk\GuestManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Share\Helper\FilesMetadataCache;
use OCA\Talk\Share\RoomShareProvider;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\Federation\ICloudIdManager;
use OCP\Files\FileInfo;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\FilesMetadata\Exceptions\FilesMetadataNotFoundException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IPreview as IPreviewManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Server;
use OCP\Share\Exceptions\ShareNotFound;
use Sabre\VObject\Reader;

/**
 * @template-implements IEventListener<Event>
 */
class SystemMessage implements IEventListener {
	protected ?IL10N $l = null;

	/**
	 * @psalm-var array<array-key, null|string>
	 */
	protected array $displayNames = [];
	/** @var string[] */
	protected array $groupNames = [];
	/** @var string[] */
	protected array $circleNames = [];
	/** @var string[] */
	protected array $circleLinks = [];
	/** @var string[] */
	protected array $guestNames = [];
	/** @var array<string, array<string, string>> */
	protected array $phoneNames = [];


	protected array $currentFederatedUserDetails = [];

	public function __construct(
		protected IAppConfig $appConfig,
		protected IUserManager $userManager,
		protected IGroupManager $groupManager,
		protected GuestManager $guestManager,
		protected ParticipantService $participantService,
		protected IPreviewManager $previewManager,
		protected RoomShareProvider $shareProvider,
		protected PhotoCache $photoCache,
		protected IRootFolder $rootFolder,
		protected ICloudIdManager $cloudIdManager,
		protected IURLGenerator $url,
		protected FilesMetadataCache $metadataCache,
		protected Authenticator $federationAuthenticator,
		protected IEventDispatcher $dispatcher,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof MessageParseEvent) {
			return;
		}

		if ($event->getMessage()->getMessageType() === ChatManager::VERB_SYSTEM) {
			try {
				$this->parseMessage($event->getMessage(), $event->allowInaccurate());
				// Disabled so we can parse mentions in captions: $event->stopPropagation();
			} catch (\OutOfBoundsException $e) {
				// Unknown message, ignore
			}
		} elseif ($event->getMessage()->getMessageType() === ChatManager::VERB_MESSAGE_DELETED) {
			try {
				$this->parseDeletedMessage($event->getMessage());
				$event->stopPropagation();
			} catch (\OutOfBoundsException) {
				// Unknown message, ignore
			}
		}
	}

	/**
	 * @param Message $chatMessage
	 * @throws \OutOfBoundsException
	 */
	protected function parseMessage(Message $chatMessage, $allowInaccurate): void {
		$this->l = $chatMessage->getL10n();
		$comment = $chatMessage->getComment();
		$room = $chatMessage->getRoom();
		$data = json_decode($chatMessage->getMessage(), true);
		if (!\is_array($data)) {
			throw new \OutOfBoundsException('Invalid message');
		}

		$message = $data['message'];
		$parameters = $data['parameters'];
		$parsedParameters = ['actor' => $this->getActorFromComment($room, $comment)];

		$participant = $chatMessage->getParticipant();
		if ($participant === null) {
			$currentActorType = null;
			$currentActorId = null;
			$currentUserIsActor = false;
		} elseif ($this->federationAuthenticator->isFederationRequest()) {
			if (empty($this->currentFederatedUserDetails)) {
				$cloudId = $this->cloudIdManager->resolveCloudId($this->federationAuthenticator->getCloudId());
				$this->currentFederatedUserDetails = [
					'user' => $cloudId->getUser(),
					'server' => $cloudId->getRemote(),
				];
			}

			$currentActorType = $participant->getAttendee()->getActorType();
			$currentActorId = $participant->getAttendee()->getActorId();
			$currentUserIsActor = isset($parsedParameters['actor']['server'])
				&& $parsedParameters['actor']['type'] === 'user'
				&& $this->currentFederatedUserDetails['user'] === $parsedParameters['actor']['id']
				&& $this->currentFederatedUserDetails['server'] === $parsedParameters['actor']['server'];
		} elseif (!$participant->isGuest()) {
			$currentActorType = $participant->getAttendee()->getActorType();
			$currentActorId = $participant->getAttendee()->getActorId();
			$currentUserIsActor = $parsedParameters['actor']['type'] === 'user'
				&& $participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS
				&& $currentActorId === $parsedParameters['actor']['id']
				&& empty($parsedParameters['actor']['server']);
		} elseif ($participant->getAttendee()->getActorType() === Attendee::ACTOR_EMAILS) {
			$currentActorType = $participant->getAttendee()->getActorType();
			$currentActorId = $participant->getAttendee()->getActorId();
			$currentUserIsActor = $parsedParameters['actor']['type'] === 'email'
				&& $participant->getAttendee()->getActorType() === Attendee::ACTOR_EMAILS
				&& $participant->getAttendee()->getActorId() === $parsedParameters['actor']['id'];
		} else {
			$currentActorType = $participant->getAttendee()->getActorType();
			$currentActorId = $participant->getAttendee()->getActorId();
			$currentUserIsActor = $parsedParameters['actor']['type'] === 'guest'
				&& $participant->getAttendee()->getActorType() === 'guest'
				&& $participant->getAttendee()->getActorId() === $parsedParameters['actor']['id'];
		}
		$cliIsActor = $parsedParameters['actor']['type'] === 'guest'
			&& 'guest/' . Attendee::ACTOR_ID_CLI === $parsedParameters['actor']['id'];

		if ($message === 'conversation_created') {
			$systemIsActor = $parsedParameters['actor']['type'] === 'guest'
				&& 'guest/' . Attendee::ACTOR_ID_SYSTEM === $parsedParameters['actor']['id'];

			$parsedMessage = $this->l->t('{actor} created the conversation');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You created the conversation');
			} elseif ($systemIsActor) {
				$parsedMessage = $this->l->t('System created the conversation');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator created the conversation');
			}
		} elseif ($message === 'conversation_renamed') {
			$parsedMessage = $this->l->t('{actor} renamed the conversation from "%1$s" to "%2$s"', [$parameters['oldName'], $parameters['newName']]);
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You renamed the conversation from "%1$s" to "%2$s"', [$parameters['oldName'], $parameters['newName']]);
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator renamed the conversation from "%1$s" to "%2$s"', [$parameters['oldName'], $parameters['newName']]);
			}
		} elseif ($message === 'description_set') {
			$parsedMessage = $this->l->t('{actor} set the description');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You set the description');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator set the description');
			}
		} elseif ($message === 'description_removed') {
			$parsedMessage = $this->l->t('{actor} removed the description');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You removed the description');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator removed the description');
			}
		} elseif ($message === 'call_started') {
			$metaData = $comment->getMetaData() ?? [];
			$silentCall = $metaData[Message::METADATA_SILENT] ?? false;
			if ($silentCall) {
				if ($currentUserIsActor) {
					$parsedMessage = $this->l->t('You started a silent call');
					if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
						$parsedMessage = $this->l->t('Outgoing silent call');
					}
				} else {
					$parsedMessage = $this->l->t('{actor} started a silent call');
					if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
						$parsedMessage = $this->l->t('Incoming silent call');
					}
				}
			} else {
				if ($currentUserIsActor) {
					$parsedMessage = $this->l->t('You started a call');
					if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
						$parsedMessage = $this->l->t('Outgoing call');
					}
				} else {
					$parsedMessage = $this->l->t('{actor} started a call');
					if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
						$parsedMessage = $this->l->t('Incoming call');
					}
				}
			}
		} elseif ($message === 'call_joined') {
			$parsedMessage = $this->l->t('{actor} joined the call');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You joined the call');
			}
		} elseif ($message === 'call_left') {
			$parsedMessage = $this->l->t('{actor} left the call');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You left the call');
			}
		} elseif ($message === 'call_missed') {
			[$parsedMessage, $parsedParameters, $message] = $this->parseMissedCall($room, $parameters, $currentActorType === Attendee::ACTOR_FEDERATED_USERS ? null : $currentActorId);
		} elseif ($message === 'call_ended' || $message === 'call_ended_everyone') {
			[$parsedMessage, $parsedParameters] = $this->parseCall($room, $message, $parameters, $parsedParameters);
		} elseif ($message === 'read_only_off') {
			$parsedMessage = $this->l->t('{actor} unlocked the conversation');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You unlocked the conversation');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator unlocked the conversation');
			}
		} elseif ($message === 'read_only') {
			$parsedMessage = $this->l->t('{actor} locked the conversation');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You locked the conversation');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator locked the conversation');
			}
		} elseif ($message === 'listable_none') {
			$parsedMessage = $this->l->t('{actor} limited the conversation to the current participants');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You limited the conversation to the current participants');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator limited the conversation to the current participants');
			}
		} elseif ($message === 'listable_users') {
			$parsedMessage = $this->l->t('{actor} opened the conversation to registered users');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You opened the conversation to registered users');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator opened the conversation to registered users');
			}
		} elseif ($message === 'listable_all') {
			$parsedMessage = $this->l->t('{actor} opened the conversation to registered users and users created with the Guests app');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You opened the conversation to registered users and users created with the Guests app');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator opened the conversation to registered users and users created with the Guests app');
			}
		} elseif ($message === 'lobby_timer_reached') {
			$parsedMessage = $this->l->t('The conversation is now open to everyone');
		} elseif ($message === 'lobby_none') {
			$parsedMessage = $this->l->t('{actor} opened the conversation to everyone');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You opened the conversation to everyone');
			}
		} elseif ($message === 'lobby_non_moderators') {
			$parsedMessage = $this->l->t('{actor} restricted the conversation to moderators');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You restricted the conversation to moderators');
			}
		} elseif ($message === 'breakout_rooms_started') {
			$parsedMessage = $this->l->t('{actor} started breakout rooms');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You started breakout rooms');
			}
		} elseif ($message === 'breakout_rooms_stopped') {
			$parsedMessage = $this->l->t('{actor} stopped breakout rooms');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You stopped breakout rooms');
			}
		} elseif ($message === 'guests_allowed') {
			$parsedMessage = $this->l->t('{actor} allowed guests');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You allowed guests');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator allowed guests');
			}
		} elseif ($message === 'guests_disallowed') {
			$parsedMessage = $this->l->t('{actor} disallowed guests');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You disallowed guests');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator disallowed guests');
			}
		} elseif ($message === 'password_set') {
			$parsedMessage = $this->l->t('{actor} set a password');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You set a password');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator set a password');
			}
		} elseif ($message === 'password_removed') {
			$parsedMessage = $this->l->t('{actor} removed the password');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You removed the password');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator removed the password');
			}
		} elseif ($message === 'user_added') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			$parsedMessage = $this->l->t('{actor} added {user}');
			if ($parsedParameters['user']['id'] === $parsedParameters['actor']['id']) {
				if ($currentUserIsActor) {
					$parsedMessage = $this->l->t('You joined the conversation');
				} else {
					$parsedMessage = $this->l->t('{actor} joined the conversation');
				}
			} elseif ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You added {user}');
			} elseif ($this->isCurrentParticipantChangedUser($currentActorType, $currentActorId, $parsedParameters['user'])) {
				$parsedMessage = $this->l->t('{actor} added you');
				if ($cliIsActor) {
					$parsedMessage = $this->l->t('An administrator added you');
				}
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator added {user}');
			}
		} elseif ($message === 'user_removed') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			if ($parsedParameters['user']['id'] === $parsedParameters['actor']['id']) {
				if ($currentUserIsActor) {
					$parsedMessage = $this->l->t('You left the conversation');
				} else {
					$parsedMessage = $this->l->t('{actor} left the conversation');
				}
			} else {
				$parsedMessage = $this->l->t('{actor} removed {user}');
				if ($currentUserIsActor) {
					$parsedMessage = $this->l->t('You removed {user}');
				} elseif ($this->isCurrentParticipantChangedUser($currentActorType, $currentActorId, $parsedParameters['user'])) {
					$parsedMessage = $this->l->t('{actor} removed you');
					if ($cliIsActor) {
						$parsedMessage = $this->l->t('An administrator removed you');
					}
				} elseif ($cliIsActor) {
					$parsedMessage = $this->l->t('An administrator removed {user}');
				}
			}
		} elseif ($message === 'federated_user_added') {
			$parsedParameters['federated_user'] = $this->getRemoteUser($room, $parameters['federated_user']);
			$parsedMessage = $this->l->t('{actor} invited {federated_user}');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You invited {federated_user}');
				if ($this->isFederatedUserThemselvesActor($parsedParameters['federated_user'], $parsedParameters['actor'])) {
					$parsedMessage = $this->l->t('You accepted the invitation');
				}
			} elseif ($this->isCurrentParticipantChangedUser($currentActorType, $currentActorId, $parsedParameters['federated_user'])) {
				$parsedMessage = $this->l->t('{actor} invited you');
				if ($cliIsActor) {
					$parsedMessage = $this->l->t('An administrator invited you');
				}
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator invited {federated_user}');
			} elseif ($this->isFederatedUserThemselvesActor($parsedParameters['federated_user'], $parsedParameters['actor'])) {
				$parsedMessage = $this->l->t('{federated_user} accepted the invitation');
			}
		} elseif ($message === 'federated_user_removed') {
			$parsedParameters['federated_user'] = $this->getRemoteUser($room, $parameters['federated_user']);
			$parsedMessage = $this->l->t('{actor} removed {federated_user}');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You removed {federated_user}');
				if ($this->isFederatedUserThemselvesActor($parsedParameters['federated_user'], $parsedParameters['actor'])) {
					$parsedMessage = $this->l->t('You declined the invitation');
				}
			} elseif ($this->isCurrentParticipantChangedUser($currentActorType, $currentActorId, $parsedParameters['federated_user'])) {
				$parsedMessage = $this->l->t('{actor} removed you');
				if ($cliIsActor) {
					$parsedMessage = $this->l->t('An administrator removed you');
				}
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator removed {federated_user}');
			} elseif ($this->isFederatedUserThemselvesActor($parsedParameters['federated_user'], $parsedParameters['actor'])) {
				$parsedMessage = $this->l->t('{federated_user} declined the invitation');
			}
		} elseif ($message === 'group_added') {
			$parsedParameters['group'] = $this->getGroup($parameters['group']);
			$parsedMessage = $this->l->t('{actor} added group {group}');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You added group {group}');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator added group {group}');
			}
		} elseif ($message === 'group_removed') {
			$parsedParameters['group'] = $this->getGroup($parameters['group']);
			$parsedMessage = $this->l->t('{actor} removed group {group}');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You removed group {group}');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator removed group {group}');
			}
		} elseif ($message === 'circle_added') {
			$parsedParameters['circle'] = $this->getCircle($parameters['circle']);
			$parsedMessage = $this->l->t('{actor} added team {circle}');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You added team {circle}');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator added team {circle}');
			}
		} elseif ($message === 'circle_removed') {
			$parsedParameters['circle'] = $this->getCircle($parameters['circle']);
			$parsedMessage = $this->l->t('{actor} removed team {circle}');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You removed team {circle}');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator removed team {circle}');
			}
		} elseif ($message === 'phone_added') {
			$parsedParameters['phone'] = $this->getPhone($room, $parameters['phone'], $parameters['name']);
			$parsedMessage = $this->l->t('{actor} added {phone}');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You added {phone}');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator added {phone}');
			}
		} elseif ($message === 'phone_removed') {
			$parsedParameters['phone'] = $this->getPhone($room, $parameters['phone'], $parameters['name']);
			$parsedMessage = $this->l->t('{actor} removed {phone}');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You removed {phone}');
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator removed {phone}');
			}
		} elseif ($message === 'moderator_promoted') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			$parsedMessage = $this->l->t('{actor} promoted {user} to moderator');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You promoted {user} to moderator');
			} elseif ($this->isCurrentParticipantChangedUser($currentActorType, $currentActorId, $parsedParameters['user'])) {
				$parsedMessage = $this->l->t('{actor} promoted you to moderator');
				if ($cliIsActor) {
					$parsedMessage = $this->l->t('An administrator promoted you to moderator');
				}
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator promoted {user} to moderator');
			}
		} elseif ($message === 'moderator_demoted') {
			$parsedParameters['user'] = $this->getUser($parameters['user']);
			$parsedMessage = $this->l->t('{actor} demoted {user} from moderator');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You demoted {user} from moderator');
			} elseif ($this->isCurrentParticipantChangedUser($currentActorType, $currentActorId, $parsedParameters['user'])) {
				$parsedMessage = $this->l->t('{actor} demoted you from moderator');
				if ($cliIsActor) {
					$parsedMessage = $this->l->t('An administrator demoted you from moderator');
				}
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator demoted {user} from moderator');
			}
		} elseif ($message === 'guest_moderator_promoted') {
			if (isset($parameters['type'], $parameters['id'])) {
				$parsedParameters['user'] = $this->getGuest($room, $parameters['type'], $parameters['id']);
			} else {
				// Before Nextcloud 30
				$parsedParameters['user'] = $this->getGuest($room, Attendee::ACTOR_GUESTS, $parameters['session']);
			}
			$parsedMessage = $this->l->t('{actor} promoted {user} to moderator');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You promoted {user} to moderator');
			} elseif ($this->isCurrentParticipantChangedUser($currentActorType, $currentActorId, $parsedParameters['user'])) {
				$parsedMessage = $this->l->t('{actor} promoted you to moderator');
				if ($cliIsActor) {
					$parsedMessage = $this->l->t('An administrator promoted you to moderator');
				}
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator promoted {user} to moderator');
			}
		} elseif ($message === 'guest_moderator_demoted') {
			if (isset($parameters['type'], $parameters['id'])) {
				$parsedParameters['user'] = $this->getGuest($room, $parameters['type'], $parameters['id']);
			} else {
				// Before Nextcloud 30
				$parsedParameters['user'] = $this->getGuest($room, Attendee::ACTOR_GUESTS, $parameters['session']);
			}
			$parsedMessage = $this->l->t('{actor} demoted {user} from moderator');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You demoted {user} from moderator');
			} elseif ($this->isCurrentParticipantChangedUser($currentActorType, $currentActorId, $parsedParameters['user'])) {
				$parsedMessage = $this->l->t('{actor} demoted you from moderator');
				if ($cliIsActor) {
					$parsedMessage = $this->l->t('An administrator demoted you from moderator');
				}
			} elseif ($cliIsActor) {
				$parsedMessage = $this->l->t('An administrator demoted {user} from moderator');
			}
		} elseif ($message === 'file_shared') {
			try {
				$parsedParameters['file'] = $this->getFileFromShare($room, $participant, $parameters['share'], $allowInaccurate);
				$parsedMessage = '{file}';
				$metaData = $parameters['metaData'] ?? [];
				if (isset($metaData['messageType'])) {
					if ($metaData['messageType'] === ChatManager::VERB_VOICE_MESSAGE) {
						$chatMessage->setMessageType(ChatManager::VERB_VOICE_MESSAGE);
					} elseif ($metaData['messageType'] === ChatManager::VERB_RECORD_AUDIO) {
						$chatMessage->setMessageType(ChatManager::VERB_RECORD_AUDIO);
					} elseif ($metaData['messageType'] === ChatManager::VERB_RECORD_VIDEO) {
						$chatMessage->setMessageType(ChatManager::VERB_RECORD_VIDEO);
					} else {
						$chatMessage->setMessageType(ChatManager::VERB_MESSAGE);
					}
				} else {
					$chatMessage->setMessageType(ChatManager::VERB_MESSAGE);
				}

				if (isset($metaData['caption']) && $metaData['caption'] !== '') {
					$parsedMessage = $metaData['caption'];
				}
			} catch (\Exception) {
				$chatMessage->setMessageType(ChatManager::VERB_MESSAGE);
				$parsedMessage = $this->l->t('{actor} shared a file which is no longer available');
				if ($currentUserIsActor) {
					$parsedMessage = $this->l->t('You shared a file which is no longer available');
				} elseif ($currentActorType === Attendee::ACTOR_FEDERATED_USERS) {
					$parsedMessage = $this->l->t('File shares are currently not supported in federated conversations');
				}
				$parsedMessage = '*' . $parsedMessage . '*';

				$metaData = $parameters['metaData'] ?? [];
				if (isset($metaData['caption']) && $metaData['caption'] !== '') {
					$parsedMessage .= "\n\n" . $metaData['caption'];
				}
			}
		} elseif ($message === 'object_shared') {
			$parsedParameters['object'] = $parameters['metaData'];
			$parsedParameters['object']['id'] = (string)$parsedParameters['object']['id'];
			$parsedMessage = '{object}';

			if (isset($parsedParameters['object']['type'])
				&& $parsedParameters['object']['type'] === 'geo-location'
				&& !preg_match(ChatManager::GEO_LOCATION_VALIDATOR, $parsedParameters['object']['id'])) {
				$parsedParameters = [];
				$parsedMessage = $this->l->t('The shared location is malformed');
			}

			$chatMessage->setMessageType(ChatManager::VERB_MESSAGE);
		} elseif ($message === 'matterbridge_config_added') {
			$parsedMessage = $this->l->t('{actor} set up Matterbridge to synchronize this conversation with other chats');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You set up Matterbridge to synchronize this conversation with other chats');
			}
		} elseif ($message === 'thread_created') {
			$parsedMessage = $this->l->t('{actor} created thread {title}');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You created thread {title}');
			}
			$parsedParameters['title'] = [
				'type' => 'highlight',
				'id' => 'thread/' . $parameters['thread'],
				'name' => $parameters['title'] ?? (string)$parameters['thread'],
			];
		} elseif ($message === 'thread_renamed') {
			$parsedMessage = $this->l->t('{actor} renamed thread {title}');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You renamed thread {title}');
			}
			$parsedParameters['title'] = [
				'type' => 'highlight',
				'id' => 'thread/' . $parameters['thread'],
				'name' => $parameters['title'],
			];
		} elseif ($message === 'matterbridge_config_edited') {
			$parsedMessage = $this->l->t('{actor} updated the Matterbridge configuration');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You updated the Matterbridge configuration');
			}
		} elseif ($message === 'matterbridge_config_removed') {
			$parsedMessage = $this->l->t('{actor} removed the Matterbridge configuration');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You removed the Matterbridge configuration');
			}
		} elseif ($message === 'matterbridge_config_enabled') {
			$parsedMessage = $this->l->t('{actor} started Matterbridge');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You started Matterbridge');
			}
		} elseif ($message === 'matterbridge_config_disabled') {
			$parsedMessage = $this->l->t('{actor} stopped Matterbridge');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You stopped Matterbridge');
			}
		} elseif ($message === 'message_deleted') {
			$parsedMessage = $this->l->t('{actor} deleted a message');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You deleted a message');
			}
		} elseif ($message === 'message_edited') {
			$parsedMessage = $this->l->t('{actor} edited a message');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You edited a message');
			}
		} elseif ($message === 'reaction_revoked') {
			$parsedMessage = $this->l->t('{actor} deleted a reaction');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You deleted a reaction');
			}
		} elseif ($message === 'message_expiration_enabled') {
			$weeks = $parameters['seconds'] >= (86400 * 7) ? (int)round($parameters['seconds'] / (86400 * 7)) : 0;
			$days = $parameters['seconds'] >= 86400 ? (int)round($parameters['seconds'] / 86400) : 0;
			$hours = $parameters['seconds'] >= 3600 ? (int)round($parameters['seconds'] / 3600) : 0;
			$minutes = (int)round($parameters['seconds'] / 60);

			if ($currentUserIsActor) {
				if ($weeks > 0) {
					$parsedMessage = $this->l->n('You set the message expiration to %n week', 'You set the message expiration to %n weeks', $weeks);
				} elseif ($days > 0) {
					$parsedMessage = $this->l->n('You set the message expiration to %n day', 'You set the message expiration to %n days', $days);
				} elseif ($hours > 0) {
					$parsedMessage = $this->l->n('You set the message expiration to %n hour', 'You set the message expiration to %n hours', $hours);
				} else {
					$parsedMessage = $this->l->n('You set the message expiration to %n minute', 'You set the message expiration to %n minutes', $minutes);
				}
			} else {
				if ($weeks > 0) {
					$parsedMessage = $this->l->n('{actor} set the message expiration to %n week', '{actor} set the message expiration to %n weeks', $weeks);
				} elseif ($days > 0) {
					$parsedMessage = $this->l->n('{actor} set the message expiration to %n day', '{actor} set the message expiration to %n days', $days);
				} elseif ($hours > 0) {
					$parsedMessage = $this->l->n('{actor} set the message expiration to %n hour', '{actor} set the message expiration to %n hours', $hours);
				} else {
					$parsedMessage = $this->l->n('{actor} set the message expiration to %n minute', '{actor} set the message expiration to %n minutes', $minutes);
				}
			}
		} elseif ($message === 'message_expiration_disabled') {
			$parsedMessage = $this->l->t('{actor} disabled message expiration');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You disabled message expiration');
			}
		} elseif ($message === 'history_cleared') {
			$parsedMessage = $this->l->t('{actor} cleared the history of the conversation');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You cleared the history of the conversation');
			}
		} elseif ($message === 'avatar_set') {
			$parsedMessage = $this->l->t('{actor} set the conversation picture');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You set the conversation picture');
			}
		} elseif ($message === 'avatar_removed') {
			$parsedMessage = $this->l->t('{actor} removed the conversation picture');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You removed the conversation picture');
			}
		} elseif ($message === 'poll_closed') {
			$parsedParameters['poll'] = $parameters['poll'];
			$parsedParameters['poll']['id'] = (string)$parsedParameters['poll']['id'];
			$parsedMessage = $this->l->t('{actor} ended the poll {poll}');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You ended the poll {poll}');
			}
		} elseif ($message === 'recording_started') {
			$parsedMessage = $this->l->t('{actor} started the video recording');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You started the video recording');
			}
		} elseif ($message === 'recording_stopped') {
			$parsedMessage = $this->l->t('{actor} stopped the video recording');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You stopped the video recording');
			}
		} elseif ($message === 'audio_recording_started') {
			$parsedMessage = $this->l->t('{actor} started the audio recording');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You started the audio recording');
			}
		} elseif ($message === 'audio_recording_stopped') {
			$parsedMessage = $this->l->t('{actor} stopped the audio recording');
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('You stopped the audio recording');
			}
		} elseif ($message === 'recording_failed') {
			$parsedMessage = $this->l->t('The recording failed');
		} elseif ($message === 'poll_voted') {
			$parsedParameters['poll'] = $parameters['poll'];
			$parsedParameters['poll']['id'] = (string)$parsedParameters['poll']['id'];
			$parsedMessage = $this->l->t('Someone voted on the poll {poll}');
			unset($parsedParameters['actor']);

			$chatMessage->setActor(Attendee::ACTOR_GUESTS, Attendee::ACTOR_ID_SYSTEM, '');
		} else {
			throw new \OutOfBoundsException('Unknown subject');
		}

		$chatMessage->setMessage($parsedMessage, $parsedParameters, $message);
	}

	/**
	 * @param Message $chatMessage
	 * @throws \OutOfBoundsException
	 */
	protected function parseDeletedMessage(Message $chatMessage): void {
		$this->l = $chatMessage->getL10n();
		$data = json_decode($chatMessage->getMessage(), true);
		if (!\is_array($data)) {
			throw new \OutOfBoundsException('Invalid message');
		}
		$room = $chatMessage->getRoom();

		$parsedParameters = ['actor' => $this->getActor($room, $data['deleted_by_type'], $data['deleted_by_id'])];

		$participant = $chatMessage->getParticipant();

		$authorIsActor = $data['deleted_by_type'] === $chatMessage->getComment()->getActorType()
			&& $data['deleted_by_id'] === $chatMessage->getComment()->getActorId();

		if ($participant === null) {
			$currentUserIsActor = false;
		} elseif ($this->federationAuthenticator->isFederationRequest()) {
			if (empty($this->currentFederatedUserDetails)) {
				$cloudId = $this->cloudIdManager->resolveCloudId($this->federationAuthenticator->getCloudId());
				$this->currentFederatedUserDetails = [
					'user' => $cloudId->getUser(),
					'server' => $cloudId->getRemote(),
				];
			}

			$currentUserIsActor = isset($parsedParameters['actor']['server'])
				&& $parsedParameters['actor']['type'] === 'user'
				&& $this->currentFederatedUserDetails['user'] === $parsedParameters['actor']['id']
				&& $this->currentFederatedUserDetails['server'] === $parsedParameters['actor']['server'];
		} elseif (!$participant->isGuest()) {
			$currentUserIsActor = $parsedParameters['actor']['type'] === 'user'
				&& $participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS
				&& $participant->getAttendee()->getActorId() === $parsedParameters['actor']['id']
				&& empty($parsedParameters['actor']['server']);
		} else {
			$currentUserIsActor = $parsedParameters['actor']['type'] === 'guest'
				&& $participant->getAttendee()->getActorType() === 'guest'
				&& $participant->getAttendee()->getActorId() === $parsedParameters['actor']['id'];
		}

		if ($chatMessage->getMessageType() === ChatManager::VERB_MESSAGE_DELETED) {
			$message = 'message_deleted';
			$parsedMessage = $this->l->t('Message deleted by author');

			if (!$authorIsActor) {
				$parsedMessage = $this->l->t('Message deleted by {actor}');
			}
			if ($currentUserIsActor) {
				$parsedMessage = $this->l->t('Message deleted by you');
			}
		} else {
			throw new \OutOfBoundsException('Unknown subject');
		}

		// Overwrite reactions of deleted messages as you can not react to them anymore either
		$chatMessage->getComment()->setReactions([]);

		$chatMessage->setMessage($parsedMessage, $parsedParameters, $message);
	}

	/**
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws ShareNotFound
	 */
	protected function getFileFromShare(Room $room, ?Participant $participant, string $shareId, bool $allowInaccurate): array {
		$share = $this->shareProvider->getShareById((int)$shareId);

		if ($participant && $participant->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
			if ($allowInaccurate) {
				$node = $share->getNodeCacheEntry();
				if ($node === null) {
					throw new ShareNotFound();
				}

				$name = $node->getName();
				$size = $node->getSize();
				$path = $name;
			} elseif ($share->getShareOwner() !== $participant->getAttendee()->getActorId()) {
				$userFolder = $this->rootFolder->getUserFolder($participant->getAttendee()->getActorId());
				if (!$userFolder instanceof Node) {
					throw new ShareNotFound();
				}

				$node = $userFolder->getFirstNodeById($share->getNodeId());
				if (!$node instanceof Node) {
					// FIXME This should be much more sensible, e.g.
					// 1. Only be executed on "Waiting for new messages"
					// 2. Once per request
					\OC_Util::tearDownFS();
					\OC_Util::setupFS($participant->getAttendee()->getActorId());
					$userNodes = $userFolder->getById($share->getNodeId());

					if (empty($userNodes)) {
						throw new NotFoundException('File was not found');
					}

					/** @var Node $node */
					$node = reset($userNodes);
				}

				$fullPath = $node->getPath();
				$pathSegments = explode('/', $fullPath, 4);
				$name = $node->getName();
				$size = $node->getSize();
				$path = $pathSegments[3] ?? $name;
			} else {
				$node = $share->getNode();
				$name = $node->getName();
				$size = $node->getSize();

				$fullPath = $node->getPath();
				$pathSegments = explode('/', $fullPath, 4);
				$path = $pathSegments[3] ?? $name;
			}

			$url = $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', [
				'fileid' => $node->getId(),
			]);
		} elseif ($participant && $room->getType() !== Room::TYPE_PUBLIC && $participant->getAttendee()->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
			throw new ShareNotFound();
		} else {
			if ($allowInaccurate) {
				$node = $share->getNodeCacheEntry();
			} else {
				$node = $share->getNode();
				$this->dispatcher->dispatchTyped(new OverwritePublicSharePropertiesEvent($share));
			}

			$name = $node->getName();
			$size = $node->getSize();
			$path = $name;

			$url = $this->url->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', [
				'token' => $share->getToken(),
			]);
		}

		$fileId = $node->getId();
		if ($node instanceof FileInfo) {
			$isPreviewAvailable = $this->previewManager->isAvailable($node);
		} else {
			$isPreviewAvailable = $size > 0 && $this->previewManager->isMimeSupported($node->getMimeType());
		}

		$data = [
			'type' => 'file',
			'id' => (string)$fileId,
			'name' => $name,
			'size' => (string)$size,
			'path' => $path,
			'link' => $url,
			'etag' => $node->getEtag(),
			'permissions' => (string)$node->getPermissions(),
			'mimetype' => $node->getMimeType(),
			'preview-available' => $isPreviewAvailable ? 'yes' : 'no',
			'hide-download' => $share->getHideDownload() ? 'yes' : 'no',
		];

		// If a preview is available, check if we can get the dimensions of the file from the metadata API
		if ($isPreviewAvailable && str_starts_with($node->getMimeType(), 'image/')) {
			try {
				$sizeMetadata = $this->metadataCache->getImageMetadataForFileId($fileId);
				if (isset($sizeMetadata['width'], $sizeMetadata['height'])) {
					$data['width'] = (string)$sizeMetadata['width'];
					$data['height'] = (string)$sizeMetadata['height'];
				}

				if (isset($sizeMetadata['blurhash'])) {
					$data['blurhash'] = $sizeMetadata['blurhash'];
				}
			} catch (FilesMetadataNotFoundException) {
			}
		}

		if ($node instanceof FileInfo && $node->getMimeType() === 'text/vcard') {
			$vCard = $node->getContent();

			$vObject = Reader::read($vCard);
			if (!empty($vObject->FN)) {
				$data['contact-name'] = (string)$vObject->FN;
			}

			$photo = $this->photoCache->getPhotoFromVObject($vObject);
			if ($photo) {
				$data['contact-photo-mimetype'] = $photo['Content-Type'];
				$data['contact-photo'] = base64_encode($photo['body']);
			}
		}

		return $data;
	}

	protected function isCurrentParticipantChangedUser(?string $currentActorType, ?string $currentActorId, array $parameter): bool {
		if ($currentActorType === Attendee::ACTOR_GUESTS) {
			return $parameter['type'] === 'guest' && $currentActorId === $parameter['id'];
		}
		if ($currentActorType === Attendee::ACTOR_EMAILS) {
			return $parameter['type'] === 'guest' && 'email/' . $currentActorId === $parameter['id'];
		}

		if (isset($parameter['server'])
			&& $currentActorType === Attendee::ACTOR_FEDERATED_USERS
			&& $parameter['type'] === 'user') {
			return $this->currentFederatedUserDetails['user'] === $parameter['id']
				&& $this->currentFederatedUserDetails['server'] === $parameter['server'];
		}

		return $currentActorType === Attendee::ACTOR_USERS && $parameter['type'] === 'user' && $currentActorId === $parameter['id'] && empty($parameter['server']);
	}

	protected function isFederatedUserThemselvesActor(array $federated, array $actor): bool {
		return isset($federated['server'], $actor['server'])
			&& $federated['id'] === $actor['id']
			&& $federated['server'] === $actor['server'];
	}

	protected function getActorFromComment(Room $room, IComment $comment): array {
		return $this->getActor($room, $comment->getActorType(), $comment->getActorId());
	}

	protected function getActor(Room $room, string $actorType, string $actorId): array {
		if ($actorType === Attendee::ACTOR_GUESTS || $actorType === Attendee::ACTOR_EMAILS) {
			return $this->getGuest($room, $actorType, $actorId);
		}
		if ($actorType === Attendee::ACTOR_PHONES) {
			return $this->getPhone($room, $actorId, '');
		}
		if ($actorType === Attendee::ACTOR_FEDERATED_USERS) {
			return $this->getRemoteUser($room, $actorId);
		}

		return $this->getUser($actorId);
	}

	protected function getUser(string $uid): array {
		if (!isset($this->displayNames[$uid])) {
			try {
				$this->displayNames[$uid] = $this->getDisplayName($uid);
			} catch (ParticipantNotFoundException $e) {
				$this->displayNames[$uid] = null;
			}
		}

		if ($this->displayNames[$uid] === null) {
			return [
				'type' => 'highlight',
				'id' => ICommentsManager::DELETED_USER,
				'name' => $this->l->t('Deleted user'),
			];
		}

		return [
			'type' => 'user',
			'id' => $uid,
			'name' => $this->displayNames[$uid],
			'mention-id' => $uid,
		];
	}

	protected function getRemoteUser(Room $room, string $federationId): array {
		$cloudId = $this->cloudIdManager->resolveCloudId($federationId);
		$displayName = $cloudId->getDisplayId();
		try {
			$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_FEDERATED_USERS, $federationId);
			$displayName = $participant->getAttendee()->getDisplayName();
		} catch (ParticipantNotFoundException) {
		}

		return [
			'type' => 'user',
			'id' => $cloudId->getUser(),
			'name' => $displayName,
			'server' => $cloudId->getRemote(),
			'mention-id' => 'federated_user/' . $cloudId->getUser() . '@' . $cloudId->getRemote(),
		];
	}

	protected function getDisplayName(string $uid): string {
		$userName = $this->userManager->getDisplayName($uid);
		if ($userName !== null) {
			return $userName;
		}

		throw new ParticipantNotFoundException();
	}

	protected function getGroup(string $gid): array {
		if (!isset($this->groupNames[$gid])) {
			$this->groupNames[$gid] = $this->getDisplayNameGroup($gid);
		}

		return [
			'type' => 'group',
			'id' => $gid,
			'name' => $this->groupNames[$gid],
			'mention-id' => 'user-group/' . $gid,
		];
	}

	protected function getPhone(Room $room, string $actorId, string $fallbackDisplayName): array {
		if (!isset($this->phoneNames[$room->getToken()][$actorId])) {
			$this->phoneNames[$room->getToken()][$actorId] = $this->getDisplayNamePhone($room, $actorId, $fallbackDisplayName);
		}

		return [
			'type' => 'highlight',
			'id' => $actorId,
			'name' => $this->phoneNames[$room->getToken()][$actorId],
		];
	}

	protected function getCircle(string $circleId): array {
		if (!isset($this->circleNames[$circleId])) {
			$this->loadCircleDetails($circleId);
		}

		if (!isset($this->circleNames[$circleId])) {
			return [
				'type' => 'highlight',
				'id' => $circleId,
				'name' => $circleId,
			];
		}

		return [
			'type' => 'circle',
			'id' => $circleId,
			'name' => $this->circleNames[$circleId],
			'link' => $this->circleLinks[$circleId],
			'mention-id' => 'team/' . $circleId,
		];
	}

	protected function getDisplayNamePhone(Room $room, string $actorId, string $fallbackDisplayName): string {
		try {
			$participant = $this->participantService->getParticipantByActor($room, Attendee::ACTOR_PHONES, $actorId);
			return $participant->getAttendee()->getDisplayName();
		} catch (ParticipantNotFoundException) {
			if ($fallbackDisplayName) {
				return $fallbackDisplayName;
			}
			return $this->l->t('Unknown number');
		}
	}

	protected function getDisplayNameGroup(string $gid): string {
		$group = $this->groupManager->get($gid);
		if ($group instanceof IGroup) {
			return $group->getDisplayName();
		}
		return $gid;
	}

	protected function loadCircleDetails(string $circleId): void {
		try {
			$circlesManager = Server::get(CirclesManager::class);
			$circlesManager->startSuperSession();
			$circle = $circlesManager->getCircle($circleId);
			$circlesManager->stopSession();

			$this->circleNames[$circleId] = $circle->getDisplayName();
			$this->circleLinks[$circleId] = $circle->getUrl();
		} catch (\Exception $e) {
			$circlesManager->stopSession();
		}
	}

	protected function getGuest(Room $room, string $actorType, string $actorId): array {
		$key = $room->getId() . '/' . $actorType . '/' . $actorId;
		if (!isset($this->guestNames[$key])) {
			$this->guestNames[$key] = $this->getGuestName($room, $actorType, $actorId);
		}

		return [
			'type' => 'guest',
			'id' => ($actorType === Attendee::ACTOR_GUESTS ? 'guest/' : 'email/') . $actorId,
			'name' => $this->guestNames[$key],
			'mention-id' => ($actorType === Attendee::ACTOR_GUESTS ? 'guest/' : 'email/') . $actorId,
		];
	}

	protected function getGuestName(Room $room, string $actorType, string $actorId): string {
		if ($actorId === Attendee::ACTOR_ID_CLI) {
			// TRANSLATORS Actor name when a chat message was done by an administration person via the commmand line
			return $this->l->t('Administration');
		}
		if ($actorId === Attendee::ACTOR_ID_SYSTEM) {
			// TRANSLATORS Actor name when a chat message was done by the system instead of an actual actor
			return $this->l->t('System');
		}
		if ($actorId === Attendee::ACTOR_ID_SAMPLE
			|| $actorId === Attendee::ACTOR_ID_CHANGELOG) {
			// Will be set by the Changelog Parser
			return $this->l->t('Guest');
		}

		try {
			$participant = $this->participantService->getParticipantByActor($room, $actorType, $actorId);
			$name = $participant->getAttendee()->getDisplayName();
			if ($name === '' && $actorType === Attendee::ACTOR_EMAILS) {
				$name = $actorId;
			} elseif ($name === '') {
				return $this->l->t('Guest');
			}
			return $this->l->t('%s (guest)', [$name]);
		} catch (ParticipantNotFoundException $e) {
			return $this->l->t('Guest');
		}
	}

	protected function parseMissedCall(Room $room, array $parameters, ?string $currentActorId): array {
		if ($parameters['users'][0] !== $currentActorId) {
			return [
				$this->l->t('Missed call'),
				[
					'user' => $this->getUser($parameters['users'][0]),
				],
				'call_missed',
			];
		}

		if ($room->getType() !== Room::TYPE_ONE_TO_ONE) {
			// Can happen if a user was remove from a one-to-one room.
			return [
				$this->l->t('Unanswered call'),
				[
					'user' => [
						'type' => 'highlight',
						'id' => ICommentsManager::DELETED_USER,
						'name' => $room->getName(),
					],
				],
				'call_tried',
			];
		}

		$participants = json_decode($room->getName(), true);
		$other = '';
		foreach ($participants as $participant) {
			if ($participant !== $currentActorId) {
				$other = $participant;
			}
		}

		return [
			$this->l->t('Unanswered call'),
			[
				'user' => $this->getUser($other),
			],
			'call_tried',
		];
	}


	protected function parseCall(Room $room, string $message, array $parameters, array $params): array {
		$actorIsSystem = $params['actor']['type'] === 'guest' && $params['actor']['id'] === 'guest/' . Attendee::ACTOR_ID_SYSTEM;
		$maxDuration = $this->appConfig->getAppValueInt('max_call_duration');
		$maxDurationWasReached = $message === 'call_ended_everyone' && $actorIsSystem && $maxDuration > 0 && $parameters['duration'] > $maxDuration;

		if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
			$subject = $this->l->t('Call ended (Duration {duration})');
			$subject = str_replace('{duration}', $this->getDuration($parameters['duration']), $subject);
			return [
				$subject,
				$params,
			];
		}

		if ($message === 'call_ended_everyone') {
			if ($params['actor']['type'] === 'user') {
				$entry = array_keys($parameters['users'], $params['actor']['id'], true);
				foreach ($entry as $i) {
					unset($parameters['users'][$i]);
				}
			} elseif (!$actorIsSystem) {
				$parameters['guests']--;
			}
		}
		if (!isset($parameters['cloudIds'])) {
			// Compatibility with old messages
			$parameters['cloudIds'] = [];
		}
		sort($parameters['users']);
		sort($parameters['cloudIds']);

		$numRealUsers = count($parameters['users']);
		$numUsers = $numRealUsers + count($parameters['cloudIds']);
		$displayedUsers = $numUsers;

		switch ($numUsers) {
			case 0:
				if ($parameters['guests'] === 0) {
					// Call without users and guests
					if ($maxDurationWasReached) {
						$subject = $this->l->t('Call was ended, as it reached the maximum call duration (Duration {duration})');
					} elseif ($message === 'call_ended') {
						$subject = $this->l->t('Call ended (Duration {duration})');
					} else {
						$subject = $this->l->t('{actor} ended the call (Duration {duration})');
					}
				} else {
					if ($maxDurationWasReached) {
						$subject = $this->l->n(
							'Call with %n guest was ended, as it reached the maximum call duration (Duration {duration})',
							'Call with %n guests was ended, as it reached the maximum call duration (Duration {duration})',
							$parameters['guests']
						);
					} elseif ($message === 'call_ended') {
						$subject = $this->l->n(
							'Call with %n guest ended (Duration {duration})',
							'Call with %n guests ended (Duration {duration})',
							$parameters['guests']
						);
					} else {
						$subject = $this->l->n(
							'{actor} ended the call with %n guest (Duration {duration})',
							'{actor} ended the call with %n guests (Duration {duration})',
							$parameters['guests']
						);
					}
				}
				break;
			case 1:
				if ($parameters['guests'] === 0) {
					if ($maxDurationWasReached) {
						$subject = $this->l->t('Call with {user1} was ended, as it reached the maximum call duration (Duration {duration})');
					} elseif ($message === 'call_ended') {
						$subject = $this->l->t('Call with {user1} ended (Duration {duration})');
					} else {
						$subject = $this->l->t('{actor} ended the call with {user1} (Duration {duration})');
					}
				} else {
					if ($maxDurationWasReached) {
						$subject = $this->l->t('Call with {user1} and {user2} was ended, as it reached the maximum call duration (Duration {duration})');
					} elseif ($message === 'call_ended') {
						$subject = $this->l->t('Call with {user1} and {user2} ended (Duration {duration})');
					} else {
						$subject = $this->l->t('{actor} ended the call with {user1} and {user2} (Duration {duration})');
					}
					$subject = str_replace('{user2}', $this->l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 2:
				if ($parameters['guests'] === 0) {
					if ($maxDurationWasReached) {
						$subject = $this->l->t('Call with {user1} and {user2} was ended, as it reached the maximum call duration (Duration {duration})');
					} elseif ($message === 'call_ended') {
						$subject = $this->l->t('Call with {user1} and {user2} ended (Duration {duration})');
					} else {
						$subject = $this->l->t('{actor} ended the call with {user1} and {user2} (Duration {duration})');
					}
				} else {
					if ($maxDurationWasReached) {
						$subject = $this->l->t('Call with {user1}, {user2} and {user3} was ended, as it reached the maximum call duration (Duration {duration})');
					} elseif ($message === 'call_ended') {
						$subject = $this->l->t('Call with {user1}, {user2} and {user3} ended (Duration {duration})');
					} else {
						$subject = $this->l->t('{actor} ended the call with {user1}, {user2} and {user3} (Duration {duration})');
					}
					$subject = str_replace('{user3}', $this->l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 3:
				if ($parameters['guests'] === 0) {
					if ($maxDurationWasReached) {
						$subject = $this->l->t('Call with {user1}, {user2} and {user3} was ended, as it reached the maximum call duration (Duration {duration})');
					} elseif ($message === 'call_ended') {
						$subject = $this->l->t('Call with {user1}, {user2} and {user3} ended (Duration {duration})');
					} else {
						$subject = $this->l->t('{actor} ended the call with {user1}, {user2} and {user3} (Duration {duration})');
					}
				} else {
					if ($maxDurationWasReached) {
						$subject = $this->l->t('Call with {user1}, {user2}, {user3} and {user4} was ended, as it reached the maximum call duration (Duration {duration})');
					} elseif ($message === 'call_ended') {
						$subject = $this->l->t('Call with {user1}, {user2}, {user3} and {user4} ended (Duration {duration})');
					} else {
						$subject = $this->l->t('{actor} ended the call with {user1}, {user2}, {user3} and {user4} (Duration {duration})');
					}
					$subject = str_replace('{user4}', $this->l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 4:
				if ($parameters['guests'] === 0) {
					if ($maxDurationWasReached) {
						$subject = $this->l->t('Call with {user1}, {user2}, {user3} and {user4} was ended, as it reached the maximum call duration (Duration {duration})');
					} elseif ($message === 'call_ended') {
						$subject = $this->l->t('Call with {user1}, {user2}, {user3} and {user4} ended (Duration {duration})');
					} else {
						$subject = $this->l->t('{actor} ended the call with {user1}, {user2}, {user3} and {user4} (Duration {duration})');
					}
				} else {
					if ($maxDurationWasReached) {
						$subject = $this->l->t('Call with {user1}, {user2}, {user3}, {user4} and {user5} was ended, as it reached the maximum call duration (Duration {duration})');
					} elseif ($message === 'call_ended') {
						$subject = $this->l->t('Call with {user1}, {user2}, {user3}, {user4} and {user5} ended (Duration {duration})');
					} else {
						$subject = $this->l->t('{actor} ended the call with {user1}, {user2}, {user3}, {user4} and {user5} (Duration {duration})');
					}
					$subject = str_replace('{user5}', $this->l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 5:
			default:
				if ($maxDurationWasReached) {
					$subject = $this->l->t('Call with {user1}, {user2}, {user3}, {user4} and {user5} was ended, as it reached the maximum call duration (Duration {duration})');
				} elseif ($message === 'call_ended') {
					$subject = $this->l->t('Call with {user1}, {user2}, {user3}, {user4} and {user5} ended (Duration {duration})');
				} else {
					$subject = $this->l->t('{actor} ended the call with {user1}, {user2}, {user3}, {user4} and {user5} (Duration {duration})');
				}
				if ($numUsers === 5 && $parameters['guests'] === 0) {
					$displayedUsers = 5;
				} else {
					$displayedUsers = 4;
					$numOthers = $parameters['guests'] + $numUsers - $displayedUsers;
					$subject = str_replace('{user5}', $this->l->n('%n other', '%n others', $numOthers), $subject);
				}
		}

		if ($displayedUsers > 0) {
			for ($i = 1; $i <= $displayedUsers; $i++) {
				if ($i <= $numRealUsers) {
					$params['user' . $i] = $this->getUser($parameters['users'][$i - 1]);
				} else {
					$params['user' . $i] = $this->getRemoteUser($room, $parameters['cloudIds'][$i - $numRealUsers - 1]);
				}
			}
		}

		$subject = str_replace('{duration}', $this->getDuration($parameters['duration']), $subject);
		return [
			$subject,
			$params,
		];
	}

	protected function getDuration(int $seconds): string {
		$hours = floor($seconds / 3600);
		$seconds %= 3600;
		$minutes = floor($seconds / 60);
		$seconds %= 60;

		if ($hours > 0) {
			$duration = sprintf('%1$d:%2$02d:%3$02d', $hours, $minutes, $seconds);
		} else {
			$duration = sprintf('%1$d:%2$02d', $minutes, $seconds);
		}

		return $duration;
	}
}
