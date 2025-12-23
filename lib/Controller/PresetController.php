<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * @psalm-import-type TalkConversationPreset from ResponseDefinitions
 */
class PresetController extends AEnvironmentAwareOCSController {

	public function __construct(
		string $appName,
		IRequest $request,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the list of available presets
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkConversationPreset>, array{}>
	 *
	 * 200: Successfully got presets
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/preset', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	public function getPresets(): DataResponse {
		return new DataResponse([], Http::STATUS_OK);
	}
}
