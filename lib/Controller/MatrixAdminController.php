<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Config;
use OCA\Talk\Matrix\Client\Exception\MatrixException;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IRequest;

/**
 * @psalm-import-type TalkMatrixHomeserver from \OCA\Talk\ResponseDefinitions
 */
class MatrixAdminController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly HomeserverService $homeserverService,
		private readonly IAppConfig $appConfig,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List configured homeservers
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkMatrixHomeserver>, array{}>
	 *
	 * 200: Homeservers returned
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/matrix/admin/homeserver', requirements: ['apiVersion' => '(v1)'])]
	public function listHomeservers(): DataResponse {
		return new DataResponse(array_map(static fn ($hs) => $hs->jsonSerialize(), $this->homeserverService->getAll()));
	}

	/**
	 * Add a homeserver (resolves .well-known and validates /versions)
	 *
	 * @param string $serverName Matrix server name, e.g. example.org
	 * @param string $name Label shown to users
	 * @param string $baseUrl Optional client API base URL override
	 * @return DataResponse<Http::STATUS_CREATED, TalkMatrixHomeserver, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 *
	 * 201: Homeserver added
	 * 400: Invalid or duplicate server name
	 * 502: Server unreachable or not a Matrix homeserver
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/admin/homeserver', requirements: ['apiVersion' => '(v1)'])]
	public function addHomeserver(string $serverName, string $name = '', string $baseUrl = ''): DataResponse {
		try {
			$homeserver = $this->homeserverService->add($name, $serverName, $baseUrl !== '' ? $baseUrl : null);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (MatrixException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse($homeserver->jsonSerialize(), Http::STATUS_CREATED);
	}

	/**
	 * Update a homeserver
	 *
	 * @param string $id Homeserver id
	 * @param ?string $name New label
	 * @param ?bool $enabled Whether users may link accounts on it
	 * @param ?string $baseUrl New client API base URL
	 * @return DataResponse<Http::STATUS_OK, TalkMatrixHomeserver, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 *
	 * 200: Homeserver updated
	 * 404: Homeserver not found
	 * 502: New base URL is not a Matrix homeserver
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/matrix/admin/homeserver/{id}', requirements: ['apiVersion' => '(v1)', 'id' => '\d+'])]
	public function updateHomeserver(string $id, ?string $name = null, ?bool $enabled = null, ?string $baseUrl = null): DataResponse {
		try {
			$homeserver = $this->homeserverService->update($id, array_filter([
				'name' => $name,
				'enabled' => $enabled,
				'baseUrl' => $baseUrl,
			], static fn ($v) => $v !== null));
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'homeserver'], Http::STATUS_NOT_FOUND);
		} catch (MatrixException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse($homeserver->jsonSerialize());
	}

	/**
	 * Test the connection to a homeserver (re-fetches /versions)
	 *
	 * @param string $id Homeserver id
	 * @return DataResponse<Http::STATUS_OK, TalkMatrixHomeserver, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 *
	 * 200: Connection works
	 * 404: Homeserver not found
	 * 502: Server unreachable
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/admin/homeserver/{id}/test', requirements: ['apiVersion' => '(v1)', 'id' => '\d+'])]
	public function testHomeserver(string $id): DataResponse {
		try {
			$homeserver = $this->homeserverService->refreshVersions($id);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'homeserver'], Http::STATUS_NOT_FOUND);
		} catch (MatrixException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse($homeserver->jsonSerialize());
	}

	/**
	 * Remove a homeserver
	 *
	 * @param string $id Homeserver id
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Homeserver removed
	 * 404: Homeserver not found
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/matrix/admin/homeserver/{id}', requirements: ['apiVersion' => '(v1)', 'id' => '\d+'])]
	public function removeHomeserver(string $id): DataResponse {
		try {
			$this->homeserverService->remove($id);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'homeserver'], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse(null);
	}

	/**
	 * Update feature toggle and group restriction
	 *
	 * @param ?bool $enabled Enable Matrix rooms
	 * @param ?list<string> $allowedGroups Groups allowed to link (empty = everyone)
	 * @return DataResponse<Http::STATUS_OK, array{enabled: bool, allowedGroups: list<string>}, array{}>
	 *
	 * 200: Settings stored
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/matrix/admin/settings', requirements: ['apiVersion' => '(v1)'])]
	public function updateSettings(?bool $enabled = null, ?array $allowedGroups = null): DataResponse {
		if ($enabled !== null) {
			$this->appConfig->setAppValueBool(Config::MATRIX_ENABLED, $enabled);
		}
		if ($allowedGroups !== null) {
			$this->appConfig->setAppValueArray(Config::MATRIX_ALLOWED_GROUPS, array_values(array_filter($allowedGroups, 'is_string')));
		}
		return new DataResponse([
			'enabled' => $this->appConfig->getAppValueBool(Config::MATRIX_ENABLED),
			'allowedGroups' => $this->appConfig->getAppValueArray(Config::MATRIX_ALLOWED_GROUPS),
		]);
	}
}
