<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\Talk\DataObjects\AccountId;
use OCA\Talk\DataObjects\RegisterAccountData;
use OCA\Talk\Exceptions\HostedSignalingServerAPIException;
use OCA\Talk\Exceptions\HostedSignalingServerInputException;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

/**
 * API documentation at https://gitlab.com/strukturag/spreed-hpbservice/-/blob/master/doc/API.md
 */
class HostedSignalingServerService {
	/** @var mixed */
	private $apiServerUrl;

	public function __construct(
		private IConfig $config,
		private IClientService $clientService,
		private LoggerInterface $logger,
		private IL10N $l10n,
		private ISecureRandom $secureRandom,
	) {

		$this->apiServerUrl = $this->config->getSystemValue('talk_hardcoded_hpb_service', 'https://api.spreed.cloud');
	}

	/**
	 * @throws HostedSignalingServerAPIException
	 * @throws HostedSignalingServerInputException
	 */
	public function registerAccount(RegisterAccountData $registerAccountData): AccountId {
		try {
			$nonce = $this->secureRandom->generate(32);
			$this->config->setAppValue('spreed', 'hosted-signaling-server-nonce', $nonce);

			$client = $this->clientService->newClient();
			$response = $client->post($this->apiServerUrl . '/v1/account', [
				'json' => [
					'url' => $registerAccountData->getUrl(),
					'name' => $registerAccountData->getName(),
					'email' => $registerAccountData->getEmail(),
					'language' => $registerAccountData->getLanguage(),
					'country' => $registerAccountData->getCountry(),
				],
				'headers' => [
					'X-Account-Service-Nonce' => $nonce,
				],
				'timeout' => 10,
			]);
		} catch (ClientException $e) {
			$response = $e->getResponse();

			if ($response === null) {
				$this->logger->error('Failed to request hosted signaling server trial', ['exception' => $e]);
				$message = $this->l10n->t('Failed to request trial because the trial server is unreachable. Please try again later.');
				throw new HostedSignalingServerAPIException($message, Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			$status = $response->getStatusCode();
			switch ($status) {
				case Http::STATUS_UNAUTHORIZED:
					$body = $response->getBody()->getContents();
					$this->logger->error('Requesting hosted signaling server trial failed: unauthorized - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('There is a problem with the authentication of this instance. Maybe it is not reachable from the outside to verify it\'s URL.');
					throw new HostedSignalingServerAPIException($message, $status);
				case Http::STATUS_BAD_REQUEST:
					$body = $response->getBody()->getContents();
					if ($body) {
						$parsedBody = json_decode($body, true);
						if (json_last_error() !== JSON_ERROR_NONE) {
							$this->logger->error('Requesting hosted signaling server trial failed: cannot parse JSON response - JSON error: ' . json_last_error() . ' ' . json_last_error_msg() . ' HTTP status: ' . $status . ' Response body: ' . $body);

							$message = $this->l10n->t('Something unexpected happened.');
							throw new HostedSignalingServerAPIException($message, $status);
						}
						if ($parsedBody['reason']) {
							$message = '';
							switch ($parsedBody['reason']) {
								case 'invalid_content_type':
									$log = 'The content type is invalid.';
									break;
								case 'invalid_json':
									$log = 'The JSON is invalid.';
									break;
								case 'missing_url':
									$log = 'The URL is missing.';
									break;
								case 'missing_name':
									$log = 'The name is missing.';
									break;
								case 'missing_email':
									$log = 'The email address is missing';
									break;
								case 'missing_language':
									$log = 'The language code is missing.';
									break;
								case 'missing_country':
									$log = 'The country code is missing.';
									break;
								case 'invalid_url':
									$message = $this->l10n->t('The URL is invalid.');
									$log = 'The entered URL is invalid.';
									break;
								case 'https_required':
									$message = $this->l10n->t('An HTTPS URL is required.');
									$log = 'An HTTPS URL is required.';
									break;
								case 'invalid_email':
									$message = $this->l10n->t('The email address is invalid.');
									$log = 'The email address is invalid.';
									break;
								case 'invalid_language':
									$message = $this->l10n->t('The language is invalid.');
									$log = 'The language is invalid.';
									break;
								case 'invalid_country':
									$message = $this->l10n->t('The country is invalid.');
									$log = 'The country is invalid.';
									break;
							}
							// user error
							if ($message !== '') {
								$this->logger->warning('Requesting hosted signaling server trial failed: bad request - reason: ' . $parsedBody['reason'] . ' ' . $log);
								throw new HostedSignalingServerAPIException($message, $status);
							}
							$this->logger->error('Requesting hosted signaling server trial failed: bad request - reason: ' . $parsedBody['reason'] . ' ' . $log);

							$message = $this->l10n->t('There is a problem with the request of the trial. Please check your logs for further information.');
							throw new HostedSignalingServerAPIException($message, $status);
						}
					}

					$message = $this->l10n->t('Something unexpected happened.');
					throw new HostedSignalingServerAPIException($message, $status);
				case Http::STATUS_TOO_MANY_REQUESTS:
					$body = $response->getBody()->getContents();
					$this->logger->error('Requesting hosted signaling server trial failed: too many requests - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('Too many requests are send from your servers address. Please try again later.');
					throw new HostedSignalingServerInputException($message, $status);
				case Http::STATUS_CONFLICT:
					$body = $response->getBody()->getContents();
					$this->logger->error('Requesting hosted signaling server trial failed: already registered - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('There is already a trial registered for this Nextcloud instance.');
					throw new HostedSignalingServerInputException($message, $status);
				case Http::STATUS_INTERNAL_SERVER_ERROR:
					$body = $response->getBody()->getContents();
					$this->logger->error('Requesting hosted signaling server trial failed: internal server error - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('Something unexpected happened. Please try again later.');
					throw new HostedSignalingServerAPIException($message, $status);
				default:
					$body = $response->getBody()->getContents();
					$this->logger->error('Requesting hosted signaling server trial failed: something else happened - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('Failed to request trial because the trial server behaved wrongly. Please try again later.');
					throw new HostedSignalingServerAPIException($message, $status);
			}
		} catch (\Exception $e) {
			$this->logger->error('Failed to request hosted signaling server trial', ['exception' => $e]);
			$message = $this->l10n->t('Failed to request trial because the trial server is unreachable. Please try again later.');
			throw new HostedSignalingServerAPIException($message, ($e instanceof ServerException ? $e->getResponse()?->getStatusCode() : null) ?? Http::STATUS_INTERNAL_SERVER_ERROR);
		} finally {
			// this is needed here because the deletion happens in a concurrent request
			// and thus the cached value in the config object would trigger an UPDATE
			// instead of an INSERT if there is another request to the API server
			$this->config->deleteAppValue('spreed', 'hosted-signaling-server-nonce');
		}

		$status = $response->getStatusCode();

		if ($status !== Http::STATUS_CREATED) {
			$body = $response->getBody();
			$this->logger->error('Requesting hosted signaling server trial failed: something else happened - HTTP status: ' . $status . ' Response body: ' . $body);

			$message = $this->l10n->t('Something unexpected happened.');
			throw new HostedSignalingServerAPIException($message, $status);
		}

		$body = $response->getBody();
		$data = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->logger->error('Requesting hosted signaling server trial failed: cannot parse JSON response - JSON error: ' . json_last_error() . ' ' . json_last_error_msg() . ' HTTP status: ' . $status . ' Response body: ' . $body);

			$message = $this->l10n->t('Something unexpected happened.');
			throw new HostedSignalingServerAPIException($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if (!isset($data['account_id'])) {
			$this->logger->error('Requesting hosted signaling server trial failed: no account ID transfered - HTTP status: ' . $status . ' Response body: ' . $body);

			$message = $this->l10n->t('Something unexpected happened.');
			throw new HostedSignalingServerAPIException($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$accountId = (string)$data['account_id'];
		$this->config->setAppValue('spreed', 'hosted-signaling-server-account-id', $accountId);

		return new AccountId($accountId);
	}

	/**
	 * @throws HostedSignalingServerAPIException
	 *
	 * @return \ArrayAccess|array{created: mixed, owner: \ArrayAccess|array{country: mixed, email: mixed, language: mixed, name: mixed, url: mixed, ...<array-key, mixed>}, status: mixed, signaling?: array, ...<array-key, mixed>}
	 */
	public function fetchAccountInfo(AccountId $accountId) {
		try {
			$nonce = $this->secureRandom->generate(32);
			$this->config->setAppValue('spreed', 'hosted-signaling-server-nonce', $nonce);

			$client = $this->clientService->newClient();
			$response = $client->get($this->apiServerUrl . '/v1/account/' . $accountId->get(), [
				'headers' => [
					'X-Account-Service-Nonce' => $nonce,
				],
				'timeout' => 10,
			]);
		} catch (ClientException $e) {
			$response = $e->getResponse();

			if ($response === null) {
				$this->logger->error('Trial requested but failed to get account information', ['exception' => $e]);
				$message = $this->l10n->t('Trial requested but failed to get account information. Please check back later.');
				throw new HostedSignalingServerAPIException($message, Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			$status = $response->getStatusCode();

			switch ($status) {
				case Http::STATUS_UNAUTHORIZED:
					$body = $response->getBody()->getContents();
					$this->logger->error('Getting the account information failed: unauthorized - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('There is a problem with the authentication of this request. Maybe it is not reachable from the outside to verify it\'s URL.');
					throw new HostedSignalingServerAPIException($message, $status);
				case Http::STATUS_BAD_REQUEST:
					$body = $response->getBody()->getContents();
					if ($body) {
						$parsedBody = json_decode($body, true);
						if (json_last_error() !== JSON_ERROR_NONE) {
							$this->logger->error('Getting the account information failed: cannot parse JSON response - JSON error: ' . json_last_error() . ' ' . json_last_error_msg() . ' HTTP status: ' . $status . ' Response body: ' . $body);

							$message = $this->l10n->t('Something unexpected happened.');
							throw new HostedSignalingServerAPIException($message, $status);
						}
						if ($parsedBody['reason']) {
							switch ($parsedBody['reason']) {
								case 'missing_account_id':
									$log = 'The account ID is missing.';
									break;
								default:
									$body = $response->getBody()->getContents();
									$this->logger->error('Getting the account information failed: something else happened - HTTP status: ' . $status . ' Response body: ' . $body);

									$message = $this->l10n->t('Failed to fetch account information because the trial server behaved wrongly. Please check back later.');
									throw new HostedSignalingServerAPIException($message, $status);
							}
							$this->logger->error('Getting the account information failed: bad request - reason: ' . $parsedBody['reason'] . ' ' . $log);

							$message = $this->l10n->t('There is a problem with fetching the account information. Please check your logs for further information.');
							throw new HostedSignalingServerAPIException($message, $status);
						}
					}

					$message = $this->l10n->t('Something unexpected happened.');
					throw new HostedSignalingServerAPIException($message, $status);
				case Http::STATUS_TOO_MANY_REQUESTS:
					$body = $response->getBody()->getContents();
					$this->logger->error('Getting the account information failed: too many requests - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('Too many requests are send from your servers address. Please try again later.');
					throw new HostedSignalingServerAPIException($message, $status);
				case Http::STATUS_NOT_FOUND:
					$body = $response->getBody()->getContents();
					$this->logger->error('Getting the account information failed: account not found - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('There is no such account registered.');
					throw new HostedSignalingServerAPIException($message, $status);
				case Http::STATUS_INTERNAL_SERVER_ERROR:
					$body = $response->getBody()->getContents();
					$this->logger->error('Getting the account information failed: internal server error - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('Something unexpected happened. Please try again later.');
					throw new HostedSignalingServerAPIException($message, $status);
				default:
					$body = $response->getBody()->getContents();
					$this->logger->error('Getting the account information failed: something else happened - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('Failed to fetch account information because the trial server behaved wrongly. Please check back later.');
					throw new HostedSignalingServerAPIException($message, $status);
			}
		} catch (\Exception $e) {
			$this->logger->error('Failed to request hosted signaling server trial', ['exception' => $e]);
			$message = $this->l10n->t('Failed to fetch account information because the trial server is unreachable. Please check back later.');
			throw new HostedSignalingServerAPIException($message, ($e instanceof ServerException ? $e->getResponse()?->getStatusCode() : null) ?? Http::STATUS_INTERNAL_SERVER_ERROR);
		} finally {
			// this is needed here because the delete happens in a concurrent request
			// and thus the cached value in the config object would trigger an UPDATE
			// instead of an INSERT if there is another request to the API server
			$this->config->deleteAppValue('spreed', 'hosted-signaling-server-nonce');
		}

		$status = $response->getStatusCode();

		if ($status !== Http::STATUS_OK) {
			$body = $response->getBody();
			$this->logger->error('Getting the account information failed: something else happened - HTTP status: ' . $status . ' Response body: ' . $body);


			$message = $this->l10n->t('Something unexpected happened.');
			throw new HostedSignalingServerAPIException($message, $status);
		}

		$body = $response->getBody();
		$data = (array)json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->logger->error('Getting the account information failed: cannot parse JSON response - JSON error: ' . json_last_error() . ' ' . json_last_error_msg() . ' HTTP status: ' . $status . ' Response body: ' . $body);

			$message = $this->l10n->t('Something unexpected happened.');
			throw new HostedSignalingServerAPIException($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if (!isset($data['status'])
			|| !isset($data['created'])
			|| ($data['status'] === 'active' && (
				!isset($data['signaling'])
				|| !isset($data['signaling']['url'])
				|| !isset($data['signaling']['secret'])
			)
			)
			|| !isset($data['owner'])
			|| !isset($data['owner']['url'])
			|| !isset($data['owner']['name'])
			|| !isset($data['owner']['email'])
			|| !isset($data['owner']['language'])
			|| !isset($data['owner']['country'])
			/* TODO they are not yet returned
			|| ($data['status'] === 'active' && (
					!isset($data['limits'])
					|| !isset($data['limits']['users'])
				)
			)
			*/
			|| (in_array($data['status'], ['error', 'blocked']) && !isset($data['reason']))
			|| !in_array($data['status'], ['error', 'blocked', 'pending', 'active', 'expired'])
		) {
			$this->logger->error('Getting the account information failed: response is missing mandatory field - data: ' . json_encode($data));

			$message = $this->l10n->t('Something unexpected happened.');
			throw new HostedSignalingServerAPIException($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return $data;
	}

	/**
	 * @throws HostedSignalingServerAPIException
	 */
	public function deleteAccount(AccountId $accountId): void {
		try {
			$nonce = $this->secureRandom->generate(32);
			$this->config->setAppValue('spreed', 'hosted-signaling-server-nonce', $nonce);

			$client = $this->clientService->newClient();
			$response = $client->delete($this->apiServerUrl . '/v1/account/' . $accountId->get(), [
				'headers' => [
					'X-Account-Service-Nonce' => $nonce,
				],
				'timeout' => 10,
			]);
		} catch (ClientException $e) {
			$response = $e->getResponse();

			if ($response === null) {
				$this->logger->error('Deleting the hosted signaling server account failed', ['exception' => $e]);
				$message = $this->l10n->t('Deleting the hosted signaling server account failed. Please check back later.');
				throw new HostedSignalingServerAPIException($message, Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			$status = $response->getStatusCode();

			switch ($status) {
				case Http::STATUS_UNAUTHORIZED:
					$body = $response->getBody()->getContents();
					$this->logger->error('Deleting the hosted signaling server account failed: unauthorized - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('There is a problem with the authentication of this request. Maybe it is not reachable from the outside to verify it\'s URL.');
					throw new HostedSignalingServerAPIException($message, $status);
				case Http::STATUS_BAD_REQUEST:
					$body = $response->getBody()->getContents();
					if ($body) {
						$parsedBody = json_decode($body, true);
						if (json_last_error() !== JSON_ERROR_NONE) {
							$this->logger->error('Deleting the hosted signaling server account failed: cannot parse JSON response - JSON error: ' . json_last_error() . ' ' . json_last_error_msg() . ' HTTP status: ' . $status . ' Response body: ' . $body);

							$message = $this->l10n->t('Something unexpected happened.');
							throw new HostedSignalingServerAPIException($message, $status);
						}
						if ($parsedBody['reason']) {
							switch ($parsedBody['reason']) {
								case 'missing_account_id':
									$log = 'The account ID is missing.';
									break;
								default:
									$body = $response->getBody()->getContents();
									$this->logger->error('Deleting the hosted signaling server account failed: something else happened - HTTP status: ' . $status . ' Response body: ' . $body);

									$message = $this->l10n->t('Failed to delete the account because the trial server behaved wrongly. Please check back later.');
									throw new HostedSignalingServerAPIException($message, $status);
							}
							$this->logger->error('Deleting the hosted signaling server account failed: bad request - reason: ' . $parsedBody['reason'] . ' ' . $log);

							$message = $this->l10n->t('There is a problem with deleting the account. Please check your logs for further information.');
							throw new HostedSignalingServerAPIException($message, $status);
						}
					}

					$message = $this->l10n->t('Something unexpected happened.');
					throw new HostedSignalingServerAPIException($message, $status);
				case Http::STATUS_TOO_MANY_REQUESTS:
					$body = $response->getBody()->getContents();
					$this->logger->error('Deleting the hosted signaling server account failed: too many requests - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('Too many requests are sent from your servers address. Please try again later.');
					throw new HostedSignalingServerAPIException($message, $status);
				case Http::STATUS_NOT_FOUND:
					$body = $response->getBody()->getContents();
					$this->logger->error('Deleting the hosted signaling server account failed: account not found - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('There is no such account registered.');
					throw new HostedSignalingServerAPIException($message, $status);
				case Http::STATUS_INTERNAL_SERVER_ERROR:
					$body = $response->getBody()->getContents();
					$this->logger->error('Deleting the hosted signaling server account failed: internal server error - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('Something unexpected happened. Please try again later.');
					throw new HostedSignalingServerAPIException($message, $status);
				default:
					$body = $response->getBody()->getContents();
					$this->logger->error('Deleting the hosted signaling server account failed: something else happened - HTTP status: ' . $status . ' Response body: ' . $body);

					$message = $this->l10n->t('Failed to delete the account because the trial server behaved wrongly. Please check back later.');
					throw new HostedSignalingServerAPIException($message, $status);
			}
		} catch (\Exception $e) {
			$this->logger->error('Deleting the hosted signaling server account failed', ['exception' => $e]);
			$message = $this->l10n->t('Failed to delete the account because the trial server is unreachable. Please check back later.');
			throw new HostedSignalingServerAPIException($message, ($e instanceof ServerException ? $e->getResponse()?->getStatusCode() : null) ?? Http::STATUS_INTERNAL_SERVER_ERROR);
		} finally {
			// this is needed here because the delete happens in a concurrent request
			// and thus the cached value in the config object would trigger an UPDATE
			// instead of an INSERT if there is another request to the API server
			$this->config->deleteAppValue('spreed', 'hosted-signaling-server-nonce');
		}

		$status = $response->getStatusCode();

		if ($status !== Http::STATUS_NO_CONTENT) {
			$body = $response->getBody();
			$this->logger->error('Deleting the hosted signaling server account failed: something else happened - HTTP status: ' . $status . ' Response body: ' . $body);


			$message = $this->l10n->t('Something unexpected happened.');
			throw new HostedSignalingServerAPIException($message, $status);
		}
	}
}
