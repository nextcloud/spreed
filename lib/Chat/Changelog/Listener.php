<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat\Changelog;

use OCA\Talk\Config;
use OCA\Talk\Events\BeforeRoomsFetchEvent;
use OCP\AppFramework\Services\IAppConfig;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {
	public function __construct(
		private readonly Manager $manager,
		private readonly IConfig $serverConfig,
		private readonly IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof BeforeRoomsFetchEvent) {
			return;
		}

		if (!$this->appConfig->getAppValueBool(Config::CHANGELOG)) {
			return;
		}

		$this->manager->updateChangelog($event->getUserId());
	}
}
