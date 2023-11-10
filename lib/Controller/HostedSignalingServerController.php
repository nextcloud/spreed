<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Kate Döen <kate.doeen@nextcloud.com>
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

use OCA\Talk\DataObjects\AccountId;
use OCA\Talk\DataObjects\RegisterAccountData;
use OCA\Talk\Exceptions\HostedSignalingServerAPIException;
use OCA\Talk\Exceptions\HostedSignalingServerInputException;
use OCA\Talk\Service\HostedSignalingServerService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class HostedSignalingServerController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		protected IClientService $clientService,
		protected IL10N $l10n,
		protected IConfig $config,
		protected LoggerInterface $logger,
		private HostedSignalingServerService $hostedSignalingServerService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the authentication credentials
	 *
	 * @return DataResponse<Http::STATUS_OK, array{nonce: string}, array{}>|DataResponse<Http::STATUS_PRECONDITION_FAILED, array<empty>, array{}>
	 *
	 * 200: Authentication credentials returned
	 * 412: Getting authentication credentials is not possible
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[PublicPage]
	public function auth(): DataResponse {
		$storedNonce = $this->config->getAppValue('spreed', 'hosted-signaling-server-nonce', '');
		// reset nonce after one request
		$this->config->deleteAppValue('spreed', 'hosted-signaling-server-nonce');

		if ($storedNonce !== '') {
			return new DataResponse([
				'nonce' => $storedNonce,
			]);
		}

		return new DataResponse([], Http::STATUS_PRECONDITION_FAILED);
	}

	/**
	 * Request a trial account
	 *
	 * @param string $url Server URL
	 * @param string $name Display name of the user
	 * @param string $email Email of the user
	 * @param string $language Language of the user
	 * @param string $country Country of the user
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Trial requested successfully
	 * 400: Requesting trial is not possible
	 */
	public function requestTrial(string $url, string $name, string $email, string $language, string $country): DataResponse {
		try {
			$registerAccountData = new RegisterAccountData(
				$url,
				$name,
				$email,
				$language,
				$country
			);

			$accountId = $this->hostedSignalingServerService->registerAccount($registerAccountData);
			$accountInfo = $this->hostedSignalingServerService->fetchAccountInfo($accountId);
			$this->config->setAppValue('spreed', 'hosted-signaling-server-account', json_encode($accountInfo));
		} catch (HostedSignalingServerAPIException $e) { // API or connection issues
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (HostedSignalingServerInputException $e) { // user solvable issues
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}


		return new DataResponse($accountInfo);
	}

	/**
	 * Delete the account
	 *
	 * @return DataResponse<Http::STATUS_NO_CONTENT, array<empty>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 204: Account deleted successfully
	 * 400: Deleting account is not possible
	 */
	public function deleteAccount(): DataResponse {
		$accountId = $this->config->getAppValue('spreed', 'hosted-signaling-server-account-id');

		if ($accountId === null) {
			return new DataResponse(['message' => $this->l10n->t('No account available to delete.')], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->hostedSignalingServerService->deleteAccount(new AccountId($accountId));
		} catch (HostedSignalingServerAPIException $e) {
			if ($e->getCode() === Http::STATUS_NOT_FOUND) {
				// Account was deleted, so remove the information locally
			} elseif ($e->getCode() === Http::STATUS_UNAUTHORIZED) {
				// Account is expired and deletion is pending unless it's reactivated.
			} else {
				// API or connection issues - do nothing and just try again later
				return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		}

		$this->config->deleteAppValue('spreed', 'hosted-signaling-server-account');
		$this->config->deleteAppValue('spreed', 'hosted-signaling-server-account-id');

		// remove signaling servers if account is not active anymore
		$this->config->deleteAppValue('spreed', 'signaling_mode');
		$this->config->deleteAppValue('spreed', 'signaling_servers');

		$this->logger->info('Deleted hosted signaling server account with ID ' . $accountId);

		return new DataResponse([], Http::STATUS_NO_CONTENT);
	}
}
