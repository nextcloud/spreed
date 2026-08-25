<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

/**
 * @template-implements IEventListener<LoadAdditionalEntriesEvent>
 */
class LoadNavigationEntryListener implements IEventListener {

	public function __construct(
		private readonly INavigationManager $navigationManager,
		private readonly IURLGenerator $urlGenerator,
		private readonly IUserSession $userSession,
		private readonly Config $config,
		private readonly IL10N $l,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalEntriesEvent) {
			// Unrelated
			return;
		}

		$this->navigationManager->add(function (): ?array {
			$user = $this->userSession->getUser();
			if (!$user instanceof IUser || $this->config->isDisabledForUser($user)) {
				return null;
			}

			return [
				'id' => Application::APP_ID,
				'name' => $this->l->t('Talk'),
				'href' => $this->urlGenerator->linkToRouteAbsolute('spreed.Page.index'),
				'icon' => $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'),
				'order' => -5,
				'type' => INavigationManager::TYPE_APPS,
			];
		});
	}
}
