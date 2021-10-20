<?php

declare(strict_types=1);
/**
 * @copyright 2017 Ivan Sein <ivan@nextcloud.com>
 *
 * @author 2017 Ivan Sein <ivan@nextcloud.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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

	/** @var IActionFactory */
	private $actionFactory;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IUserManager */
	private $userManager;
	/** @var IL10N */
	private $l10n;
	/** @var Config */
	private $config;

	public function __construct(IActionFactory $actionFactory,
								IURLGenerator $urlGenerator,
								IL10N $l10n,
								IUserManager $userManager,
								Config $config) {
		$this->actionFactory = $actionFactory;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
		$this->l10n = $l10n;
		$this->config = $config;
	}

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
