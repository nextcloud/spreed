<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCP\DB\Events\AddMissingIndicesEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<AddMissingIndicesEvent>
 */
class AddMissingIndicesListener implements IEventListener {
	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof AddMissingIndicesEvent)) {
			// Unrelated
			return;
		}

		/**
		 * Added to @see Version3003Date20180720162342
		 */
		$event->addMissingIndex(
			'talk_rooms',
			'tr_room_object',
			['object_type', 'object_id']
		);
	}
}
