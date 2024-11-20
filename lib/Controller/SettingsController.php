<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Settings\BeforePreferenceSetEventListener;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class SettingsController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		protected IRootFolder $rootFolder,
		protected IConfig $config,
		protected IGroupManager $groupManager,
		protected LoggerInterface $logger,
		protected BeforePreferenceSetEventListener $preferenceListener,
		protected ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Update user setting
	 *
	 * @param 'attachment_folder'|'read_status_privacy'|'typing_privacy'|'play_sounds' $key Key to update
	 * @param string|int|null $value New value for the key
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST, null, array{}>
	 *
	 * 200: User setting updated successfully
	 * 400: Updating user setting is not possible
	 */
	#[NoAdminRequired]
	public function setUserSetting(string $key, string|int|null $value): DataResponse {
		if (!$this->preferenceListener->validatePreference($this->userId, $key, $value)) {
			return new DataResponse(null, Http::STATUS_BAD_REQUEST);
		}

		$this->config->setUserValue($this->userId, 'spreed', $key, $value);

		return new DataResponse(null);
	}

	/**
	 * Update SIP bridge settings
	 *
	 * @param list<string> $sipGroups New SIP groups
	 * @param string $dialInInfo New dial info
	 * @param string $sharedSecret New shared secret
	 * @return DataResponse<Http::STATUS_OK, null, array{}>
	 *
	 * 200: Successfully set new SIP settings
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_ADMINISTRATION, tags: ['settings'])]
	public function setSIPSettings(
		array $sipGroups = [],
		string $dialInInfo = '',
		string $sharedSecret = ''): DataResponse {
		$groups = [];
		foreach ($sipGroups as $gid) {
			$group = $this->groupManager->get($gid);
			if ($group instanceof IGroup) {
				$groups[] = $group->getGID();
			}
		}

		$this->config->setAppValue('spreed', 'sip_bridge_groups', json_encode($groups));
		$this->config->setAppValue('spreed', 'sip_bridge_dialin_info', $dialInInfo);
		$this->config->setAppValue('spreed', 'sip_bridge_shared_secret', $sharedSecret);

		return new DataResponse(null);
	}
}
