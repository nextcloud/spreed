<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\OCP;

use OCA\Talk\Room;
use OCP\IURLGenerator;
use OCP\Talk\IConversation;

class Conversation implements IConversation {

	public function __construct(
		protected IURLGenerator $url,
		protected Room $room,
	) {
	}

	#[\Override]
	public function getId(): string {
		return $this->room->getToken();
	}

	#[\Override]
	public function getAbsoluteUrl(): string {
		return $this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $this->room->getToken()]);
	}
}
