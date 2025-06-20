<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Flow;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;

/**
 * @template-implements IEventListener<Event>
 */
class RegisterOperationsListener implements IEventListener {

	public function __construct(
		private Operation $operation,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof RegisterOperationsEvent)) {
			// Unrelated
			return;
		}

		$event->registerOperation($this->operation);
		Util::addScript('spreed', 'talk-flow');
		Util::addStyle('spreed', 'talk-flow');
	}
}
