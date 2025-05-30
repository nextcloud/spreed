<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

use OC\User\NoUserException;
use OCA\Talk\Settings\UserPreference;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\HintException;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Util;
use Psr\Log\LoggerInterface;

trait TInitialState {
	/** @var Config */
	protected $talkConfig;
	/** @var IConfig */
	protected $serverConfig;
	/** @var IInitialState */
	protected $initialState;
	/** @var ICacheFactory */
	protected $memcacheFactory;
	protected IGroupManager $groupManager;
	protected LoggerInterface $logger;

	protected function publishInitialStateShared(): void {
		// Needed to enable the screensharing extension in Chromium < 72
		// https://chrome.google.com/webstore/detail/screensharing-for-nextclo/kepnpjhambipllfmgmbapncekcmabkol/related
		// The extension finds the element by ID and then checks if the class matches.
		// Name and content have only been added for HTML validation
		Util::addHeader('meta', ['id' => 'app', 'class' => 'nc-enable-screensharing-extension', 'name' => 'nextcloud-talk-enable-screensharing-extension', 'content' => 'true']);

		$signalingMode = $this->talkConfig->getSignalingMode();
		if ($signalingMode === Config::SIGNALING_CLUSTER_CONVERSATION
			&& !$this->memcacheFactory->isAvailable()) {
			throw new HintException(
				'High-performance backend clustering is only supported with a distributed cache!'
			);
		}

		$this->initialState->provideInitialState(
			'call_enabled',
			((int)$this->serverConfig->getAppValue('spreed', 'start_calls')) !== Room::START_CALL_NOONE
		);

		$this->initialState->provideInitialState(
			'signaling_mode',
			$this->talkConfig->getSignalingMode()
		);

		$this->initialState->provideInitialState(
			'sip_dialin_info',
			$this->talkConfig->getDialInInfo()
		);

		$this->initialState->provideInitialState(
			'grid_videos_limit',
			$this->talkConfig->getGridVideosLimit()
		);

		$this->initialState->provideInitialState(
			'grid_videos_limit_enforced',
			$this->talkConfig->getGridVideosLimitEnforced()
		);

		$this->initialState->provideInitialState(
			'federation_enabled',
			$this->talkConfig->isFederationEnabled()
		);

		$this->initialState->provideInitialState(
			'default_permissions',
			$this->talkConfig->getDefaultPermissions()
		);
	}

	protected function publishInitialStateForUser(IUser $user, IRootFolder $rootFolder, IAppManager $appManager): void {
		$this->publishInitialStateShared();

		$this->initialState->provideInitialState(
			'start_conversations',
			!$this->talkConfig->isNotAllowedToCreateConversations($user)
		);

		$this->initialState->provideInitialState(
			'circles_enabled',
			$appManager->isEnabledForUser('circles', $user)
		);

		$this->initialState->provideInitialState(
			'guests_accounts_enabled',
			$appManager->isEnabledForUser('guests', $user)
		);

		$this->initialState->provideInitialState(
			'read_status_privacy',
			$this->talkConfig->getUserReadPrivacy($user->getUID())
		);

		$this->initialState->provideInitialState(
			'typing_privacy',
			$this->talkConfig->getUserTypingPrivacy($user->getUID())
		);

		$this->initialState->provideInitialState(
			'play_sounds',
			$this->serverConfig->getUserValue($user->getUID(), 'spreed', UserPreference::PLAY_SOUNDS, 'yes') === 'yes'
		);

		$this->initialState->provideInitialState(
			'force_enable_blur_filter',
			$this->serverConfig->getUserValue($user->getUID(), 'theming', 'force_enable_blur_filter', ''));

		$this->initialState->provideInitialState(
			'user_group_ids',
			$this->groupManager->getUserGroupIds($user)
		);

		$attachmentFolder = $this->talkConfig->getAttachmentFolder($user->getUID());
		$freeSpace = 0;

		if ($attachmentFolder) {
			try {
				$userFolder = $rootFolder->getUserFolder($user->getUID());

				try {
					try {
						$folder = $userFolder->get($attachmentFolder);
						if ($folder->isShared()) {
							$this->logger->error('Talk attachment folder for user {userId} is set to a shared folder. Resetting to their root.', [
								'userId' => $user->getUID(),
							]);
							throw new NotPermittedException('Folder is shared');
						}
					} catch (NotFoundException $e) {
						$folder = $userFolder->newFolder($attachmentFolder);
					}

					$freeSpace = $folder->getFreeSpace();
				} catch (NotPermittedException $e) {
					$attachmentFolder = '/';
					$this->serverConfig->setUserValue($user->getUID(), 'spreed', UserPreference::ATTACHMENT_FOLDER, '/');
					$freeSpace = $userFolder->getFreeSpace();
				}
			} catch (NoUserException $e) {
			}
		}

		$this->initialState->provideInitialState(
			'attachment_folder',
			$attachmentFolder
		);

		$this->initialState->provideInitialState(
			'attachment_folder_free_space',
			$freeSpace
		);

		$this->initialState->provideInitialState(
			'enable_matterbridge',
			$this->serverConfig->getAppValue('spreed', 'enable_matterbridge', '0') === '1'
		);
	}

	protected function publishInitialStateForGuest(): void {
		$this->publishInitialStateShared();

		$this->initialState->provideInitialState(
			'start_conversations',
			false
		);

		$this->initialState->provideInitialState(
			'circles_enabled',
			false
		);

		$this->initialState->provideInitialState(
			'read_status_privacy',
			Participant::PRIVACY_PUBLIC
		);

		$this->initialState->provideInitialState(
			'typing_privacy',
			Participant::PRIVACY_PUBLIC
		);

		$this->initialState->provideInitialState(
			'attachment_folder',
			''
		);

		$this->initialState->provideInitialState(
			'attachment_folder_free_space',
			''
		);

		$this->initialState->provideInitialState(
			'enable_matterbridge',
			false
		);

		$this->initialState->provideInitialState(
			'play_sounds',
			false
		);
	}
}
