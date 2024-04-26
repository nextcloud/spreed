<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\SpreedCheats\SpeechToText;

use OCP\Files\File;
use OCP\SpeechToText\ISpeechToTextProvider;

class LoremIpsumSpeechToTextProvider implements ISpeechToTextProvider {

	public function getName(): string {
		return 'Lorem ipsum - Talk Integrationtests';
	}

	public function transcribeFile(File $file): string {
		if (str_contains($file->getName(), 'leave')) {
			throw new \RuntimeException('Transcription failed by name');
		}

		sleep(1); // make sure Postgres manages the order of the messages
		return 'Lorem ipsum';
	}
}
