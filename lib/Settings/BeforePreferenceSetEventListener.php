<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Settings;

use OCA\Files_Sharing\SharedStorage;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Service\ParticipantService;
use OCP\Config\BeforePreferenceSetEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class BeforePreferenceSetEventListener implements IEventListener {
	public function __construct(
		protected IRootFolder $rootFolder,
		protected ParticipantService $participantService,
		protected LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof BeforePreferenceSetEvent)) {
			// Unrelated
			return;
		}

		if ($event->getAppId() !== Application::APP_ID) {
			return;
		}

		$event->setValid($this->validatePreference(
			$event->getUserId(),
			$event->getConfigKey(),
			$event->getConfigValue(),
		));
	}

	/**
	 * @internal Make private/protected once SettingsController route was removed
	 */
	public function validatePreference(string $userId, string $key, string|int|null $value): bool {
		if ($key === UserPreference::ATTACHMENT_FOLDER) {
			return $this->validateAttachmentFolder($userId, $value);
		}

		// "boolean" yes/no
		if ($key === UserPreference::CALLS_START_WITHOUT_MEDIA
			|| $key === UserPreference::PLAY_SOUNDS
			|| $key === UserPreference::BLUR_VIRTUAL_BACKGROUND) {
			return $value === 'yes' || $value === 'no';
		}

		// "privacy" 0/1
		if ($key === UserPreference::TYPING_PRIVACY
			|| $key === UserPreference::READ_STATUS_PRIVACY) {
			$valid = is_numeric($value) && ((int)$value === Participant::PRIVACY_PRIVATE || (int)$value === Participant::PRIVACY_PUBLIC);

			if ($valid && $key === UserPreference::READ_STATUS_PRIVACY) {
				$this->participantService->updateReadPrivacyForActor(Attendee::ACTOR_USERS, $userId, (int)$value);
			}
			return $valid;
		}

		// "list-style" 'two-lines' / 'compact'
		if ($key === UserPreference::CONVERSATIONS_LIST_STYLE) {
			return $value === UserPreference::CONVERSATION_LIST_STYLE_TWO_LINES || $value === UserPreference::CONVERSATION_LIST_STYLE_COMPACT;
		}

		return false;
	}

	protected function validateAttachmentFolder(string $userId, string $value): bool {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			$node = $userFolder->get($value);
			if (!$node instanceof Folder) {
				throw new NotPermittedException('Node is not a directory');
			}
			if ($node->isShared()) {
				throw new NotPermittedException('Folder is shared');
			}
			return !$node->getStorage()->instanceOfStorage(SharedStorage::class);
		} catch (NotFoundException) {
			$userFolder->newFolder($value);
			return true;
		} catch (NotPermittedException) {
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
		}
		return false;
	}
}
