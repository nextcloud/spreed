<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Profile;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCA\Talk\Room;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Profile\ILinkAction;

class TalkCallAction implements ILinkAction {
	private ?IUser $targetUser = null;

	public function __construct(
		private Config $config,
		private IAppConfig $appConfig,
		private IL10N $l,
		private IURLGenerator $urlGenerator,
		private IUserSession $userSession,
	) {
	}

	#[\Override]
	public function preload(IUser $targetUser): void {
		$this->targetUser = $targetUser;
	}

	#[\Override]
	public function getAppId(): string {
		return Application::APP_ID;
	}

	#[\Override]
	public function getId(): string {
		return 'talk-call';
	}

	#[\Override]
	public function getDisplayId(): string {
		return $this->l->t('Call via Talk');
	}

	#[\Override]
	public function getTitle(): string {
		$visitingUser = $this->userSession->getUser();
		if (!$visitingUser || $visitingUser === $this->targetUser) {
			return $this->l->t('Open Talk');
		}
		return $this->l->t('Call %s', [$this->targetUser->getDisplayName()]);
	}

	#[\Override]
	public function getPriority(): int {
		// prioritize actions, low order ones are shown on top
		return 11;
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'icon-video-dark.svg'));
	}

	#[\Override]
	public function getTarget(): ?string {
		$visitingUser = $this->userSession->getUser();
		if (
			!$visitingUser
			|| ($this->appConfig->getAppValueInt('start_calls') === Room::START_CALL_NOONE)
			|| $this->config->isDisabledForUser($this->targetUser)
			|| $this->config->isDisabledForUser($visitingUser)
		) {
			return null;
		}
		if ($visitingUser === $this->targetUser) {
			return $this->urlGenerator->linkToRouteAbsolute('spreed.Page.index');
		}
		return $this->urlGenerator->linkToRouteAbsolute('spreed.Page.index') . '?callUser=' . $this->targetUser->getUID() . '#direct-call';
	}
}
