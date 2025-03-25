<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Profile;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Profile\ILinkAction;

class TalkAction implements ILinkAction {
	private ?IUser $targetUser = null;

	public function __construct(
		private Config $config,
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
		return 'talk';
	}

	#[\Override]
	public function getDisplayId(): string {
		return $this->l->t('Contact via Talk');
	}

	#[\Override]
	public function getTitle(): string {
		$visitingUser = $this->userSession->getUser();
		if (!$visitingUser || $visitingUser === $this->targetUser) {
			return $this->l->t('Open Talk');
		}
		return $this->l->t('Talk to %s', [$this->targetUser->getDisplayName()]);
	}

	#[\Override]
	public function getPriority(): int {
		return 10;
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg'));
	}

	#[\Override]
	public function getTarget(): ?string {
		$visitingUser = $this->userSession->getUser();
		if (
			!$visitingUser
			|| $this->config->isDisabledForUser($this->targetUser)
			|| $this->config->isDisabledForUser($visitingUser)
		) {
			return null;
		}
		if ($visitingUser === $this->targetUser) {
			return $this->urlGenerator->linkToRouteAbsolute('spreed.Page.index');
		}
		return $this->urlGenerator->linkToRouteAbsolute('spreed.Page.index') . '?callUser=' . $this->targetUser->getUID();
	}
}
