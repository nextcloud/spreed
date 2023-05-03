<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
