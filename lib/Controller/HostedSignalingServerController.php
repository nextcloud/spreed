<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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

use GuzzleHttp\Exception\ClientException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\Security\ISecureRandom;

class HostedSignalingServerController extends OCSController {

	/** @var IClientService */
	protected $clientService;
	/** @var IL10N */
	protected $l10n;
	/** @var IConfig */
	protected $config;
	/** @var ISecureRandom */
	protected $secureRandom;
	/** @var ILogger */
	protected $logger;

	public function __construct(string $appName,
								IRequest $request,
								IClientService $clientService,
								IL10N $l10n,
								IConfig $config,
								ISecureRandom $secureRandom,
								ILogger $logger) {
		parent::__construct($appName, $request);
		$this->clientService = $clientService;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->secureRandom = $secureRandom;
		$this->logger = $logger;
	}

	/**
	 * @PublicPage
	 */
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

	public function requestTrial(string $url, string $name, string $email, string $language, string $country): DataResponse {
		$client = $this->clientService->newClient();

		try {
			$nonce = $this->secureRandom->generate(32);
			$this->config->setAppValue('spreed', 'hosted-signaling-server-nonce', $nonce);
			$apiServer = $this->config->getSystemValue('talk_hardcoded_hpb_service', 'https://api.spreed.cloud');
			$response = $client->post($apiServer . '/v1/account', [
				'json' => [
					'url' => $url,
					'name' => $name,
					'email' => $email,
					'language' => $language,
					'country' => $country,
				],
				'headers' => [
					'X-Account-Service-Nonce' => $nonce,
				],
				'timeout' => 10,
			]);
		} catch(ClientException $e) {
			$response = $e->getResponse();

			if ($response === null) {
				$this->logger->logException($e, [
					'app' => 'spreed',
					'message' => 'Failed to request hosted signaling server trial',
				]);
				return new DataResponse([
					'message' => $this->l10n->t('Failed to request trial because the trial server is unreachable. Please try again later.')
				], Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			$status = $response->getStatusCode();
			switch ($status) {
				case Http::STATUS_UNAUTHORIZED:
					// TODO log it
					return new DataResponse([
						'message' => $this->l10n->t('There is a problem with the authentication of this instance. Maybe it is not reachable from the outside to verify it\'s URL.')
					], Http::STATUS_INTERNAL_SERVER_ERROR);
				case Http::STATUS_TOO_MANY_REQUESTS:
					$body = $response->getBody()->getContents();
					$this->logger->error('Requesting hosted signaling server trial failed: too many requests - HTTP status: ' . $status . ' Response body: ' . $body, ['app' => 'spreed']);
					return new DataResponse([
						'message' => $this->l10n->t('Too many requests are send from your servers address. Please try again later.')
					], Http::STATUS_TOO_MANY_REQUESTS);
				case Http::STATUS_CONFLICT:
					$body = $response->getBody()->getContents();
					$this->logger->error('Requesting hosted signaling server trial failed: already registered - HTTP status: ' . $status . ' Response body: ' . $body, ['app' => 'spreed']);
					return new DataResponse([
						'message' => $this->l10n->t('There is already a trial registered for this Nextcloud instance.')
					], Http::STATUS_CONFLICT);
				case Http::STATUS_INTERNAL_SERVER_ERROR:
					$body = $response->getBody()->getContents();
					$this->logger->error('Requesting hosted signaling server trial failed: internal server error - HTTP status: ' . $status . ' Response body: ' . $body, ['app' => 'spreed']);
					return new DataResponse([
						'message' => $this->l10n->t('Something unexpected happened. Please try again later.')
					], Http::STATUS_INTERNAL_SERVER_ERROR);
				default:
					$body = $response->getBody()->getContents();
					$this->logger->error('Requesting hosted signaling server trial failed: something else happened - HTTP status: ' . $status . ' Response body: ' . $body, ['app' => 'spreed']);
					return new DataResponse([
						'message' => $this->l10n->t('Failed to request trial because the trial server behaved wrongly. Please try again later.')
					], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		} catch (\Exception $e) {
			$this->logger->logException($e, [
				'app' => 'spreed',
				'message' => 'Failed to request hosted signaling server trial',
			]);

			return new DataResponse([
				'message' => $this->l10n->t('Failed to request trial because the trial server is unreachable. Please try again later.')
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$status = $response->getStatusCode();

		if ($status !== Http::STATUS_CREATED) {
			$body = $response->getBody();
			$this->logger->error('Requesting hosted signaling server trial failed: something else happened - HTTP status: ' . $status . ' Response body: ' . $body, ['app' => 'spreed']);
			return new DataResponse([
				'message' => $this->l10n->t('Something unexpected happened.')
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// will contain the URL that can be used to query information on the account
		$statusUrl = $response->getHeader('Location');
		// TODO handle it
		return new DataResponse([]);
	}
}
