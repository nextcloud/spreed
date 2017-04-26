<?php

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

namespace OCA\Spreed\ContactsMenu\Providers;

use OCP\Contacts\ContactsMenu\IActionFactory;
use OCP\Contacts\ContactsMenu\IEntry;
use OCP\Contacts\ContactsMenu\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * @todo move to contacts app
 */
class CallProvider implements IProvider {

	/** @var IActionFactory */
	private $actionFactory;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IL10N */
	private $l10n;

	/**
	 * @param IActionFactory $actionFactory
	 * @param IURLGenerator $urlGenerator
	 */
	public function __construct(IActionFactory $actionFactory, IURLGenerator $urlGenerator, IL10N $l10n) {
		$this->actionFactory = $actionFactory;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
	}

	/**
	 * @param IEntry $entry
	 */
	public function process(IEntry $entry) {
		$uid = $entry->getProperty('UID');

		if (is_null($uid)) {
			// Nothing to do
			return;
		}

		if ($entry->getProperty('isLocalSystemBook') !== true) {
			// Not internal user
			return;
		}

		$iconUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/video.svg'));
		$callUrl = $this->urlGenerator->linkToRouteAbsolute('spreed.page.index') . '?callUser=' . $uid;
		$action = $this->actionFactory->newLinkAction($iconUrl, $this->l10n->t('Video call'), $callUrl);
		$entry->addAction($action);
	}

}
