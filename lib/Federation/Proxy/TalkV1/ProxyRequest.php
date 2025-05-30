<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Federation\Proxy\TalkV1;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OC\Http\Client\Response;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\RemoteClientException;
use OCA\Talk\Participant;
use OCA\Talk\Settings\UserPreference;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

class ProxyRequest {
	public function __construct(
		protected IConfig $config,
		protected IClientService $clientService,
		protected LoggerInterface $logger,
		protected IFactory $l10nFactory,
		protected IUserSession $userSession,
	) {
	}

	public function overwrittenRemoteTalkHash(string $hash): string {
		$typingIndicator = $this->config->getUserValue(
			$this->userSession->getUser()?->getUID(),
			Application::APP_ID,
			UserPreference::TYPING_PRIVACY,
			Participant::PRIVACY_PRIVATE,
		);
		return sha1(json_encode([
			'remoteHash' => $hash,
			'manipulated' => [
				'config' => [
					'chat' => [
						'read-privacy',
						'typing-privacy' => $typingIndicator,
					],
					'call' => [
						'blur-virtual-background',
					],
					'conversations' => [
						'list-style',
					],
				],
			]
		]));
	}

	/**
	 * @return Http::STATUS_BAD_REQUEST
	 */
	public function logUnexpectedStatusCode(string $method, int $statusCode, string $logDetails = ''): int {
		if ($this->config->getSystemValueBool('debug')) {
			$this->logger->error('Unexpected status code ' . $statusCode . ' returned for ' . $method . ($logDetails !== '' ? "\n" . $logDetails : ''));
		} else {
			$this->logger->debug('Unexpected status code ' . $statusCode . ' returned for ' . $method . ($logDetails !== '' ? "\n" . $logDetails : ''));
		}
		return Http::STATUS_BAD_REQUEST;
	}

	protected function generateDefaultRequestOptions(
		?string $cloudId,
		#[SensitiveParameter]
		?string $accessToken,
	): array {
		$options = [
			'verify' => !$this->config->getSystemValueBool('sharing.federation.allowSelfSignedCertificates'),
			'nextcloud' => [
				'allow_local_address' => $this->config->getSystemValueBool('allow_local_remote_servers'),
			],
			'headers' => [
				'Accept' => 'application/json',
				'X-Nextcloud-Federation' => 'true',
				'OCS-APIRequest' => 'true',
				'Accept-Language' => $this->l10nFactory->getUserLanguage($this->userSession->getUser()),
			],
			'timeout' => 5,
		];

		if ($cloudId !== null && $accessToken !== null) {
			$options['auth'] = [urlencode($cloudId), $accessToken];
		}

		return $options;
	}

	protected function prependProtocolIfNotAvailable(string $url): string {
		if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
			$url = 'https://' . $url;
		}
		return $url;
	}

	/**
	 * @param 'get'|'post'|'put'|'delete' $verb
	 * @throws CannotReachRemoteException
	 */
	protected function request(
		string $verb,
		?string $cloudId,
		#[SensitiveParameter]
		?string $accessToken,
		string $url,
		array $parameters,
	): IResponse {
		$requestOptions = $this->generateDefaultRequestOptions($cloudId, $accessToken);
		if (!empty($parameters)) {
			$requestOptions['json'] = $parameters;
		}

		try {
			return $this->clientService->newClient()->{$verb}(
				$this->prependProtocolIfNotAvailable($url),
				$requestOptions
			);
		} catch (ClientException $e) {
			$status = $e->getResponse()->getStatusCode();

			try {
				$body = $e->getResponse()->getBody()->getContents();
				$data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
				$e->getResponse()->getBody()->rewind();
				if (!is_array($data)) {
					throw new \RuntimeException('JSON response is not an array');
				}
			} catch (\Throwable $e) {
				throw new CannotReachRemoteException('Error parsing JSON response', $e->getCode(), $e);
			}

			$clientException = new RemoteClientException($e->getMessage(), $status, $e, $data);
			$this->logger->debug('Client error from remote', ['exception' => $clientException]);
			return new Response($e->getResponse(), false);
		} catch (ServerException|\Throwable $e) {
			$serverException = new CannotReachRemoteException($e->getMessage(), $e->getCode(), $e);
			$this->logger->error('Could not reach remote', ['exception' => $serverException]);
			throw $serverException;
		}
	}

	/**
	 * @throws CannotReachRemoteException
	 */
	public function get(
		?string $cloudId,
		#[SensitiveParameter]
		?string $accessToken,
		string $url,
		array $parameters = [],
	): IResponse {
		return $this->request(
			'get',
			$cloudId,
			$accessToken,
			$url,
			$parameters,
		);
	}

	/**
	 * @throws CannotReachRemoteException
	 */
	public function put(
		string $cloudId,
		#[SensitiveParameter]
		string $accessToken,
		string $url,
		array $parameters = [],
	): IResponse {
		return $this->request(
			'put',
			$cloudId,
			$accessToken,
			$url,
			$parameters,
		);
	}

	/**
	 * @throws CannotReachRemoteException
	 */
	public function delete(
		string $cloudId,
		#[SensitiveParameter]
		string $accessToken,
		string $url,
		array $parameters = [],
	): IResponse {
		return $this->request(
			'delete',
			$cloudId,
			$accessToken,
			$url,
			$parameters,
		);
	}

	/**
	 * @throws CannotReachRemoteException
	 */
	public function post(
		string $cloudId,
		#[SensitiveParameter]
		string $accessToken,
		string $url,
		array $parameters = [],
	): IResponse {
		return $this->request(
			'post',
			$cloudId,
			$accessToken,
			$url,
			$parameters,
		);
	}

	/**
	 * @param list<int> $allowedStatusCodes
	 * @throws CannotReachRemoteException
	 */
	public function getOCSData(IResponse $response, array $allowedStatusCodes = [Http::STATUS_OK]): array {
		if (!in_array($response->getStatusCode(), $allowedStatusCodes, true)) {
			$this->logUnexpectedStatusCode(__METHOD__, $response->getStatusCode());
		}

		try {
			$content = $response->getBody();
			$responseData = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
			if (!is_array($responseData)) {
				throw new \RuntimeException('JSON response is not an array');
			}
		} catch (\Throwable $e) {
			$this->logger->error('Error parsing JSON response: ' . ($content ?? 'no-data'), ['exception' => $e]);
			throw new CannotReachRemoteException('Error parsing JSON response', $e->getCode(), $e);
		}

		return $responseData['ocs']['data'] ?? [];
	}
}
