<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Fon E. Noel NFEBE <me@nfebe.com>
 *
 * @author Fon E. Noel NFEBE <me@nfebe.com>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Search;

use OCA\Talk\Config;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class UnifiedSearchFilterPlugin implements IEventListener {

	public function __construct(
		protected Config $talkConfig,
		protected IUserSession $userSession,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		$currentUser = $this->userSession->getUser();
		if ($currentUser === null || $this->talkConfig->isDisabledForUser($currentUser)) {
			return;
		}

		Util::addScript('spreed', 'talk-search');
	}
}
