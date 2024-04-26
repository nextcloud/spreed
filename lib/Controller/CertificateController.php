<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Service\CertificateService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class CertificateController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected CertificateService $certificateService,
		protected IL10N $l,
		protected LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the certificate expiration for a host
	 * @param string $host Host to check
	 * @return DataResponse<Http::STATUS_OK, array{expiration_in_days: ?int}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 200: Certificate expiration returned
	 * 400: Getting certificate expiration is not possible
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION, tags: ['settings'])]
	public function getCertificateExpiration(string $host): DataResponse {
		try {
			$expirationInDays = $this->certificateService->getCertificateExpirationInDays($host);

			return new DataResponse([
				'expiration_in_days' => $expirationInDays,
			]);
		} catch (\Exception $e) {
			$this->logger->error('Failed get certificate expiration', [
				'exception' => $e,
			]);

			return new DataResponse(['message' => $this->l->t('An error occurred. Please contact your administrator.')], Http::STATUS_BAD_REQUEST);
		}
	}
}
