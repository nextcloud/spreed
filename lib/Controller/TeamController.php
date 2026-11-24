<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Service\ConversationFolderService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

class TeamController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected readonly IUserSession $userSession,
		protected readonly ConversationFolderService $conversationFolderService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the folder of a team
	 *
	 * The path is relative to the files of the current user, clients can list
	 * it with WebDAV. Files posted in the team conversation are in the
	 * `attachmentFolder` inside it, clients that show them as shared items
	 * already should leave that folder out.
	 *
	 * @param string $teamId singleID of the team
	 * @return DataResponse<Http::STATUS_OK, array{path: string, attachmentFolder: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: 'team'}, array{}>
	 *
	 * 200: Folder of the team returned
	 * 404: The team has no folder or the current user can not access it
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/team/{teamId}/folder', requirements: [
		'apiVersion' => '(v1)',
		'teamId' => '[a-zA-Z0-9]{31}',
	])]
	public function getTeamFolder(string $teamId): DataResponse {
		/** @var IUser $user */
		$user = $this->userSession->getUser();

		// Only found when the folder of the team is in the files of the user,
		// so only for those who have access to it (the members of the team)
		$folder = $this->conversationFolderService->getTeamFolder($user->getUID(), $teamId);
		if (is_null($folder)) {
			return new DataResponse(['error' => 'team'], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse([
			'path' => $this->conversationFolderService->getRelativePath($user->getUID(), $folder),
			'attachmentFolder' => ConversationFolderService::TEAM_ATTACHMENT_FOLDER,
		]);
	}
}
