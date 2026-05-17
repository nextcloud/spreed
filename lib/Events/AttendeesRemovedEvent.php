<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

class AttendeesRemovedEvent extends AttendeesEvent {

	private bool $shouldSkipLastMessageUpdate = true;

	public function shouldSkipLastMessageUpdate() : bool {
		return $this->shouldSkipLastMessageUpdate;
	}

	public function setShouldSkipLastActivityUpdate(bool $pShouldSkipLastActivityUpdate) {
		$this->shouldSkipLastMessageUpdate = $pShouldSkipLastActivityUpdate;
	}
}
