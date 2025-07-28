<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
];

$requirementsWithToken = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\LiveTranscriptionController::enable() */
		['name' => 'LiveTranscription#enable', 'url' => '/api/{apiVersion}/live-transcription/{token}', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\LiveTranscriptionController::disable() */
		['name' => 'LiveTranscription#disable', 'url' => '/api/{apiVersion}/live-transcription/{token}', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
	],
];
