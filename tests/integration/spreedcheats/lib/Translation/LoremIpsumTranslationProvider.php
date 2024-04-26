<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\SpreedCheats\Translation;

use OCP\Translation\ITranslationProvider;
use OCP\Translation\LanguageTuple;

class LoremIpsumTranslationProvider implements ITranslationProvider {
	public function getName(): string {
		return 'Lorem ipsum - Talk Integrationtests';
	}

	public function getAvailableLanguages(): array {
		return [
			new LanguageTuple(
				'en',
				'English',
				'lorem',
				'Lorem ipsum',
			)
		];
	}

	public function translate(?string $fromLanguage, string $toLanguage, string $text): string {
		return 'Lorem ipsum';
	}
}
