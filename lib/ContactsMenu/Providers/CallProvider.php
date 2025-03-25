<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\ContactsMenu\Providers;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
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

		$talkAction = $this->l10n->t('Talk to %s', [$user->getDisplayName()]);
		$iconUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('spreed', 'app-dark.svg'));
		$callUrl = $this->urlGenerator->linkToRouteAbsolute('spreed.Page.index') . '?callUser=' . $user->getUID();
		$action = $this->actionFactory->newLinkAction($iconUrl, $talkAction, $callUrl, Application::APP_ID);
		$entry->addAction($action);
	}
}
