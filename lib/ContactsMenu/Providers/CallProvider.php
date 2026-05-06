<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\ContactsMenu\Providers;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCA\Talk\Room;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Contacts\ContactsMenu\IActionFactory;
use OCP\Contacts\ContactsMenu\IEntry;
use OCP\Contacts\ContactsMenu\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;

class CallProvider implements IProvider {

	public function __construct(
		private IActionFactory $actionFactory,
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private IUserManager $userManager,
		private Config $config,
		private IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function process(IEntry $entry): void {
		$uid = $entry->getProperty('UID');

		if ($uid === null) {
			// Nothing to do
			return;
		}

		if ($entry->getProperty('isLocalSystemBook') !== true) {
			// Not internal user
			return;
		}

		$user = $this->userManager->get($uid);
		if (!$user instanceof IUser) {
			// No valid user object
			return;
		}

		if ($this->config->isDisabledForUser($user)) {
			// User can not use Talk
			return;
		}

		if ($this->appConfig->getAppValueInt('start_calls') !== Room::START_CALL_NOONE) {
			// TRANSLATORS 'Call User' - open a floating call integration
			$directTalkAction = $this->l10n->t('Call %s', [$user->getDisplayName()]);
			$directIconUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('spreed', 'icon-video-dark.svg'));
			$directCallUrl = $this->urlGenerator->linkToRouteAbsolute('spreed.Page.index') . '?callUser=' . $user->getUID() . '#direct-call';
			$directAction = $this->actionFactory->newLinkAction($directIconUrl, $directTalkAction, $directCallUrl, Application::APP_ID);
			$directAction->setPriority(30);
			$entry->addAction($directAction);
		}

		// TRANSLATORS 'Chat with User' - navigate to Talk app private conversation
		$talkAction = $this->l10n->t('Chat with %s', [$user->getDisplayName()]);
		$iconUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('spreed', 'app-dark.svg'));
		$callUrl = $this->urlGenerator->linkToRouteAbsolute('spreed.Page.index') . '?callUser=' . $user->getUID();
		$action = $this->actionFactory->newLinkAction($iconUrl, $talkAction, $callUrl, Application::APP_ID);
		$action->setPriority(10);
		$entry->addAction($action);
	}
}
