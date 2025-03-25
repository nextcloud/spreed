<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

	#[\Override]
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
