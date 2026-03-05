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
use OCP\Config\IUserConfig;
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
	protected Config $talkConfig;
	protected IConfig $serverConfig;
	protected IUserConfig $userConfig;
	protected IInitialState $initialState;
	protected ICacheFactory $memcacheFactory;
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
			'signaling_mode',
			$this->talkConfig->getSignalingMode()
		);
	}

	protected function publishInitialStateForUser(IUser $user, IRootFolder $rootFolder, IAppManager $appManager): void {
		$this->publishInitialStateShared();
	}

	protected function publishInitialStateForGuest(): void {
		$this->publishInitialStateShared();
	}
}
