<?php

declare(strict_types=1);

/**
 * @copyright 2021 Christopher Ng <chrng8@gmail.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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

	public function preload(IUser $targetUser): void {
		$this->targetUser = $targetUser;
	}

	public function getAppId(): string {
		return Application::APP_ID;
	}

	public function getId(): string {
		return 'talk';
	}

	public function getDisplayId(): string {
		return $this->l->t('Contact via Talk');
	}

	public function getTitle(): string {
		$visitingUser = $this->userSession->getUser();
		if ($visitingUser === $this->targetUser) {
			return $this->l->t('Open Talk');
		}

		return $this->l->t('Talk to %s', [$this->targetUser->getDisplayName()]);
	}

	public function getPriority(): int {
		return 10;
	}

	public function getIcon(): string {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg'));
	}

	public function getTarget(): ?string {
		$visitingUser = $this->userSession->getUser();
		if (
			$this->config->isDisabledForUser($this->targetUser)
			|| ($visitingUser && $this->config->isDisabledForUser($visitingUser))
		) {
			return null;
		}
		if ($visitingUser === $this->targetUser) {
			return $this->urlGenerator->linkToRouteAbsolute('spreed.Page.index');
		}
		return $this->urlGenerator->linkToRouteAbsolute('spreed.Page.index') . '?callUser=' . $this->targetUser->getUID();
	}
}
