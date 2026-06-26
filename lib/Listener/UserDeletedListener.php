<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Federation\FederationManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Service\ConsentService;
use OCA\Talk\Service\ConversationTagService;
use OCA\Talk\Service\PhoneService;
use OCA\Talk\Service\PollService;
use OCA\Talk\Service\ReminderService;
use OCA\Talk\Service\ScheduledMessageService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/**
 * @template-implements IEventListener<Event>
 */
class UserDeletedListener implements IEventListener {

	public function __construct(
		private readonly Manager $manager,
		private readonly PollService $pollService,
		private readonly ConsentService $consentService,
		private readonly ScheduledMessageService $messageManager,
		private readonly ConversationTagService $tagService,
		private readonly PhoneService $phoneService,
		private readonly ReminderService $reminderService,
		private readonly FederationManager $federationManager,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof UserDeletedEvent)) {
			// Unrelated
			return;
		}

		$user = $event->getUser();

		$this->manager->removeUserFromAllRooms($user);
		$this->pollService->neutralizeDeletedUser(Attendee::ACTOR_USERS, $user->getUID());
		$this->consentService->deleteByActor(Attendee::ACTOR_USERS, $user->getUID());
		$this->messageManager->deleteByActor(Attendee::ACTOR_USERS, $user->getUID());
		$this->tagService->deleteAllTagsByUserId($user->getUID());
		$this->phoneService->deleteByUser($user->getUID());
		$this->reminderService->deleteAllRemindersForUser($user->getUID());
		$this->federationManager->deleteInvitationsForUser($user->getUID());
	}
}
