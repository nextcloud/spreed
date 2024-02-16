<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Federation\Proxy\TalkV1;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Exceptions\RemoteClientException;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

class ProxyRequest {
	public function __construct(
		protected IConfig $config,
		protected IClientService $clientService,
		protected LoggerInterface $logger,
	) {
	}

	protected function generateDefaultRequestOptions(
		string $cloudId,
		#[SensitiveParameter]
		string $accessToken,
	): array {
		return  [
			'verify' => !$this->config->getSystemValueBool('sharing.federation.allowSelfSignedCertificates'),
			'nextcloud' => [
				'allow_local_address' => $this->config->getSystemValueBool('allow_local_remote_servers'),
			],
			'headers' => [
				'Accept' => 'application/json',
				'X-Nextcloud-Federation' => 'true',
				'OCS-APIRequest' => 'true',
			],
			'timeout' => 5,
			'auth' => [urlencode($cloudId), $accessToken],
		];
	}

	/**
	 * @throws CannotReachRemoteException
	 * @throws RemoteClientException
	 */
	public function get(
		string $cloudId,
		#[SensitiveParameter]
		string $accessToken,
		string $url,
		array $parameters = [],
	): IResponse {
		$requestOptions = $this->generateDefaultRequestOptions($cloudId, $accessToken);
		if (!empty($parameters)) {
			$requestOptions['json'] = $parameters;
		}

		try {
			return $this->clientService->newClient()->get($url, $requestOptions);
		} catch (ClientException $e) {
			$status = $e->getResponse()->getStatusCode();

			try {
				$body = $e->getResponse()->getBody()->getContents();
				$data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
				if (!is_array($data)) {
					throw new \RuntimeException('JSON response is not an array');
				}
			} catch (\Throwable $e) {
				throw new CannotReachRemoteException('Error parsing JSON response', $e->getCode(), $e);
			}

			$clientException = new RemoteClientException($e->getMessage(), $status, $e, $data);
			$this->logger->error('Client error from remote', ['exception' => $clientException]);
			throw $clientException;
		} catch (ServerException|\Throwable $e) {
			$serverException = new CannotReachRemoteException($e->getMessage(), $e->getCode(), $e);
			$this->logger->error('Could not reach remote', ['exception' => $serverException]);
			throw $serverException;
		}
	}
}
