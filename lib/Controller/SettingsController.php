<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

use OCA\Files_Sharing\SharedStorage;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
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
		protected ParticipantService $participantService,
		protected LoggerInterface $logger,
		protected ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Update user setting
	 *
	 * @param 'attachment_folder'|'read_status_privacy'|'typing_privacy'|'play_sounds' $key Key to update
	 * @param string|int|null $value New value for the key
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST, array<empty>, array{}>
	 *
	 * 200: User setting updated successfully
	 * 400: Updating user setting is not possible
	 */
	#[NoAdminRequired]
	public function setUserSetting(string $key, $value): DataResponse {
		if (!$this->validateUserSetting($key, $value)) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$this->config->setUserValue($this->userId, 'spreed', $key, $value);

		if ($key === 'read_status_privacy') {
			$this->participantService->updateReadPrivacyForActor(Attendee::ACTOR_USERS, $this->userId, (int) $value);
		}

		return new DataResponse();
	}

	/**
	 * @param string $setting
	 * @param int|null|string $value
	 * @return bool
	 */
	protected function validateUserSetting(string $setting, $value): bool {
		if ($setting === 'attachment_folder') {
			$userFolder = $this->rootFolder->getUserFolder($this->userId);
			try {
				$node = $userFolder->get($value);
				if (!$node instanceof Folder) {
					throw new NotPermittedException('Node is not a directory');
				}
				return !$node->getStorage()->instanceOfStorage(SharedStorage::class);
			} catch (NotFoundException $e) {
				$userFolder->newFolder($value);
				return true;
			} catch (NotPermittedException $e) {
			} catch (\Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
			return false;
		}

		if ($setting === 'typing_privacy' || $setting === 'read_status_privacy') {
			return (int) $value === Participant::PRIVACY_PUBLIC ||
				(int) $value === Participant::PRIVACY_PRIVATE;
		}
		if ($setting === 'play_sounds') {
			return $value === 'yes' || $value === 'no';
		}

		return false;
	}

	/**
	 * Update SIP bridge settings
	 *
	 * @param string[] $sipGroups New SIP groups
	 * @param string $dialInInfo New dial info
	 * @param string $sharedSecret New shared secret
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
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

		return new DataResponse();
	}
}
