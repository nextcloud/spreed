<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use Nextcloud\Matrix\Exception\MatrixException;
use OCA\Talk\Matrix\MatrixConfig;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Service\HomeserverService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;

/**
 * Administration: homeservers, feature toggles, operational settings, health.
 *
 * @psalm-import-type TalkMatrixHomeserver from \OCA\Talk\ResponseDefinitions
 */
class MatrixAdminController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly HomeserverService $homeserverService,
		private readonly MatrixConfig $config,
		private readonly AccountMapper $accountMapper,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly ITimeFactory $timeFactory,
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
	#[AuthorizedAdminSetting(settings: \OCA\Talk\Settings\Admin\AdminSettings::class)]
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
	#[AuthorizedAdminSetting(settings: \OCA\Talk\Settings\Admin\AdminSettings::class)]
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
	 * @param int $id Homeserver id
	 * @param ?string $name New label
	 * @param ?bool $enabled Whether users may link accounts on it
	 * @param ?bool $allowE2ee Whether encrypted rooms are allowed
	 * @param ?bool $allowUpload Whether file uploads are allowed
	 * @param ?string $baseUrl New client API base URL
	 * @return DataResponse<Http::STATUS_OK, TalkMatrixHomeserver, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 *
	 * 200: Homeserver updated
	 * 404: Homeserver not found
	 * 502: New base URL is not a Matrix homeserver
	 */
	#[AuthorizedAdminSetting(settings: \OCA\Talk\Settings\Admin\AdminSettings::class)]
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/matrix/admin/homeserver/{id}', requirements: ['apiVersion' => '(v1)', 'id' => '[0-9]+'])]
	public function updateHomeserver(int $id, ?string $name = null, ?bool $enabled = null, ?bool $allowE2ee = null, ?bool $allowUpload = null, ?string $baseUrl = null): DataResponse {
		try {
			$homeserver = $this->homeserverService->update($id, array_filter([
				'name' => $name,
				'enabled' => $enabled,
				'allowE2ee' => $allowE2ee,
				'allowUpload' => $allowUpload,
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
	 * @param int $id Homeserver id
	 * @return DataResponse<Http::STATUS_OK, TalkMatrixHomeserver, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_BAD_GATEWAY, array{error: string}, array{}>
	 *
	 * 200: Connection works
	 * 404: Homeserver not found
	 * 502: Server unreachable
	 */
	#[AuthorizedAdminSetting(settings: \OCA\Talk\Settings\Admin\AdminSettings::class)]
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/matrix/admin/homeserver/{id}/test', requirements: ['apiVersion' => '(v1)', 'id' => '[0-9]+'])]
	public function testHomeserver(int $id): DataResponse {
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
	 * Remove a homeserver (only possible without linked accounts)
	 *
	 * @param int $id Homeserver id
	 * @return DataResponse<Http::STATUS_OK, null, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_CONFLICT, array{error: string}, array{}>
	 *
	 * 200: Homeserver removed
	 * 404: Homeserver not found
	 * 409: Accounts are still linked to this homeserver
	 */
	#[AuthorizedAdminSetting(settings: \OCA\Talk\Settings\Admin\AdminSettings::class)]
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/matrix/admin/homeserver/{id}', requirements: ['apiVersion' => '(v1)', 'id' => '[0-9]+'])]
	public function removeHomeserver(int $id): DataResponse {
		try {
			$this->homeserverService->remove($id);
		} catch (DoesNotExistException) {
			return new DataResponse(['error' => 'homeserver'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT);
		}
		return new DataResponse(null);
	}

	/**
	 * Update feature toggle, group restriction and operational settings
	 *
	 * @param ?bool $enabled Enable Matrix rooms
	 * @param ?list<string> $allowedGroups Groups allowed to link (empty = everyone)
	 * @param ?array<string, int|bool> $settings Operational settings (syncInterval, idleSyncInterval, maxParallelSyncs, foregroundSyncAge, historyEvents, historyDays, maxUpload, typingIn, typingOut, e2eeSharedLookup, e2eeVerifiedOnly)
	 * @return DataResponse<Http::STATUS_OK, array{enabled: bool, allowedGroups: list<string>, settings: array<string, int|bool>}, array{}>
	 *
	 * 200: Settings stored
	 */
	#[AuthorizedAdminSetting(settings: \OCA\Talk\Settings\Admin\AdminSettings::class)]
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	#[ApiRoute(verb: 'PUT', url: '/api/{apiVersion}/matrix/admin/settings', requirements: ['apiVersion' => '(v1)'])]
	public function updateSettings(?bool $enabled = null, ?array $allowedGroups = null, ?array $settings = null): DataResponse {
		if ($enabled !== null) {
			$this->config->setEnabled($enabled);
		}
		if ($allowedGroups !== null) {
			$this->config->setAllowedGroupIds(array_values(array_filter($allowedGroups, 'is_string')));
		}
		if ($settings !== null) {
			$this->config->setOperationalSettings($settings);
		}
		return new DataResponse([
			'enabled' => $this->config->isEnabled(),
			'allowedGroups' => $this->config->getAllowedGroupIds(),
			'settings' => $this->config->getOperationalSettings(),
		]);
	}

	/**
	 * Sync health overview
	 *
	 * @return DataResponse<Http::STATUS_OK, array{accounts: array{total: int, active: int, error: int, disabled: int, medianSyncAge: ?int}, rooms: int, undecryptable: int, errors: list<array{mxid: string, userId: string, status: int, lastSync: ?int, error: string}>}, array{}>
	 *
	 * 200: Status returned
	 */
	#[AuthorizedAdminSetting(settings: \OCA\Talk\Settings\Admin\AdminSettings::class)]
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/matrix/admin/status', requirements: ['apiVersion' => '(v1)'])]
	public function status(): DataResponse {
		$errors = [];
		foreach ($this->accountMapper->getAll() as $account) {
			if ($account->getLastError() !== null || !$account->isActive()) {
				$errors[] = [
					'mxid' => $account->getMxid(),
					'userId' => $account->getUserId(),
					'status' => $account->getStatus(),
					'lastSync' => $account->getLastSync()?->getTimestamp(),
					'error' => (string)$account->getLastError(),
				];
			}
			if (count($errors) >= 20) {
				break;
			}
		}
		return new DataResponse([
			'accounts' => $this->accountMapper->getStatistics($this->timeFactory->getDateTime()),
			'rooms' => count($this->roomMapper->getAll()),
			'undecryptable' => $this->roomMapper->countUndecryptable(),
			'errors' => $errors,
		]);
	}
}
