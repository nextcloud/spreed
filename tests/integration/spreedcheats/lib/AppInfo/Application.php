<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\SpreedCheats\AppInfo;

use OCA\SpreedCheats\PreferenceListener;
use OCA\SpreedCheats\SpeechToText\LoremIpsumSpeechToTextProvider;
use OCA\SpreedCheats\Translation\LoremIpsumTranslationProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Config\BeforePreferenceDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'spreedcheats';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerSpeechToTextProvider(LoremIpsumSpeechToTextProvider::class);
		$context->registerTranslationProvider(LoremIpsumTranslationProvider::class);
		$context->registerEventListener(BeforePreferenceDeletedEvent::class, PreferenceListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
