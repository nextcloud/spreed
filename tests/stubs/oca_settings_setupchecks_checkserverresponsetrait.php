<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Settings\SetupChecks {
	use Generator;

	trait CheckServerResponseTrait {
		protected function serverConfigHelp(): string {
		}
	}

	trait CheckServerResponseTrait2 {
		protected function serverConfigHelp(): string {
		}

		protected function runHEAD(string $url, bool $ignoreSSL = true, bool $httpErrors = true): Generator {
		}

		protected function runRequest(string $method, string $url, array $options = []): Generator {
		}
	}
}
