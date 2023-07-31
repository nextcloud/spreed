<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Marcel Müller <marcel.mueller@nextcloud.com>
 *
 * @author Marcel Müller <marcel.mueller@nextcloud.com>
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

namespace OCA\Talk\Controller;

use OCA\Talk\Service\CertificateService;
use OCP\AppFramework\Http;
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
