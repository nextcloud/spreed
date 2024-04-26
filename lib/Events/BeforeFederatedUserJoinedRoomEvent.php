<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Events;

class BeforeFederatedUserJoinedRoomEvent extends FederatedUserJoinedRoomEvent {
	protected bool $cancelJoin = false;

	public function isJoinCanceled(): bool {
		return $this->cancelJoin;
	}
	public function cancelJoin(): void {
		$this->cancelJoin = true;
	}
}
