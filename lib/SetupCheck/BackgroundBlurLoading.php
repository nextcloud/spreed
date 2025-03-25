<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\SetupCheck;

use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\SetupCheck\CheckServerResponseTrait;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;
use Psr\Log\LoggerInterface;

/**
 * Check whether the WASM URLs works
 */
class BackgroundBlurLoading implements ISetupCheck {
	use CheckServerResponseTrait;

	public function __construct(
		protected IL10N $l10n,
		protected IConfig $config,
		protected IURLGenerator $urlGenerator,
		protected IClientService $clientService,
		protected LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function getCategory(): string {
		return 'talk';
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('Background blur');
	}

	#[\Override]
	public function run(): SetupResult {
		$url = $this->urlGenerator->linkTo('spreed', 'js/tflite.wasm');
		$noResponse = true;
		$responses = $this->runRequest('HEAD', $url);
		foreach ($responses as $response) {
			$noResponse = false;
			if ($response->getStatusCode() === 200) {
				return SetupResult::success();
			}
		}

		if ($noResponse) {
			return SetupResult::info(
				$this->l10n->t('Could not check for WASM loading support. Please check manually if your web server serves `.wasm` files.') . "\n" . $this->serverConfigHelp(),
				$this->urlGenerator->linkToDocs('admin-nginx'),
			);
		}
		return SetupResult::warning(
			$this->l10n->t('Your web server is not properly set up to deliver `.wasm` files. This is typically an issue with the Nginx configuration. For background blur it needs an adjustment to also deliver `.wasm` files. Compare your Nginx configuration to the recommended configuration in our documentation.'),
			$this->urlGenerator->linkToDocs('admin-nginx'),
		);

	}
}
