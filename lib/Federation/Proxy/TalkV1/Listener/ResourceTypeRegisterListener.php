<?php

declare(strict_types=1);
/*
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
 *
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Federation\Proxy\TalkV1\Listener;

use OCA\Talk\Config;
use OCA\Talk\Federation\CloudFederationProviderTalk;
use OCA\Talk\Federation\FederationManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\OCM\Events\ResourceTypeRegisterEvent;
use OCP\OCM\IOCMProvider;

/**
 * @template-implements IEventListener<Event>
 */
class ResourceTypeRegisterListener implements IEventListener {

	public function __construct(
		protected Config $talkConfig,
		protected IOCMProvider $provider,
		protected CloudFederationProviderTalk $talkProvider,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof ResourceTypeRegisterEvent) {
			// Unrelated
			return;
		}

		if (!$this->talkConfig->isFederationEnabled()) {
			return;
		}

		$event->registerResourceType(
			FederationManager::TALK_ROOM_RESOURCE,
			$this->talkProvider->getSupportedShareTypes(),
			[
				'talk-v1' => '/ocs/v2.php/apps/spreed/api/',
			]
		);
	}
}
