<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Command\MatrixHomeserver;

use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;
use OCP\Console\OutputFormat;

#[AsCommand(
	name: 'talk:matrix-homeserver:list',
	description: 'List the Matrix homeservers users may link accounts on',
	supportsOutputFormat: true,
)]
class ListHomeservers {
	public function __construct(
		private readonly HomeserverService $homeserverService,
	) {
	}

	public function __invoke(
		IOutput $output,
		OutputFormat $outputFormat,
	): ExitCode {
		$output->writeTableInOutputFormat(array_map(static function (Homeserver $homeserver) use ($outputFormat): array {
			$data = $homeserver->jsonSerialize();
			if ($outputFormat === OutputFormat::Plain) {
				$data['specVersions'] = implode(', ', $data['specVersions']);
			}
			return $data;
		}, $this->homeserverService->getAll()));
		return ExitCode::Success;
	}
}
