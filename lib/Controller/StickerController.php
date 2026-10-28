<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\StickerService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-import-type TalkStickerPack from ResponseDefinitions
 */
class StickerController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly StickerService $stickerService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the sticker packs of the user
	 *
	 * Required capability: `stickers`
	 *
	 * A sticker is sent by sharing its file with the conversation.
	 * The list is empty when stickers are disabled (`config => chat => stickers-folder`
	 * is empty) or the user has no access to the folder.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkStickerPack>, array{}>
	 *
	 * 200: Sticker packs returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/stickers', requirements: [
		'apiVersion' => '(v1)',
	])]
	public function getStickerPacks(): DataResponse {
		$user = $this->userSession->getUser();
		$packs = $user === null ? [] : $this->stickerService->getPacks($user->getUID());
		return new DataResponse($packs, Http::STATUS_OK);
	}
}
