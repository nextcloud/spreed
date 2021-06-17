<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\Talk\Deck;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\Util;

class DeckPluginLoader implements IEventListener {
	/** @var IRequest */
	private $request;

	public function __construct(IRequest $request) {
		$this->request = $request;
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		if (!$event->isLoggedIn()) {
			return;
		}

		if (strpos($this->request->getPathInfo(), '/apps/deck') === 0) {
			Util::addScript('spreed', 'talk-collections');
			Util::addScript('spreed', 'talk-deck');
		}
	}
}
