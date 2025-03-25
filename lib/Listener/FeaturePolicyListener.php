<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCP\AppFramework\Http\FeaturePolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Security\FeaturePolicy\AddFeaturePolicyEvent;

/**
 * @template-implements IEventListener<Event>
 */
class FeaturePolicyListener implements IEventListener {
	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof AddFeaturePolicyEvent)) {
			return;
		}

		$policy = new FeaturePolicy();
		$policy->addAllowedCameraDomain('\'self\'');
		$policy->addAllowedMicrophoneDomain('\'self\'');
		$event->addPolicy($policy);
	}
}
