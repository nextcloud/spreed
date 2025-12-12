<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\ResponseDefinitions;
use OCA\Talk\RoomPresets\IPreset;
use OCA\Talk\Service\RoomPresetFactory;
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
		protected readonly RoomPresetFactory $factory,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the list of available presets
	 *
	 * Required capability: `conversation-presets`
	 *
	 * Presets come with 2 special presets: `forced` and `default`:
	 * - The default contains the before "presets" state by default, but administration
	 *   can set different default values, which will then be preselected, but can
	 *   still be changed by users.
	 * - If a parameter is listed in forced, the value will always be used,
	 *   independent of the user selection or the value from the preset.
	 * So order of applying is:
	 * "default" preset > selected preset > user selection > "forced" preset
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkConversationPreset>, array{}>
	 *
	 * 200: Successfully got presets
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/presets/room', requirements: [
		'apiVersion' => '(v1)',
	])]
	public function getPresets(): DataResponse {
		$presets = array_values(array_map(static fn (IPreset $preset) => $preset->toArray(), $this->factory->getPresets()));
		return new DataResponse($presets, Http::STATUS_OK);
	}
}
