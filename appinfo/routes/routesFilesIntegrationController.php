<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirementsFile = [
	'apiVersion' => '(v1)',
	'fileId' => '.+',
];
$requirementsShare = [
	'apiVersion' => '(v1)',
	'shareToken' => '.+',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\FilesIntegrationController::getRoomByFileId() */
		['name' => 'FilesIntegration#getRoomByFileId', 'url' => '/api/{apiVersion}/file/{fileId}', 'verb' => 'GET', 'requirements' => $requirementsFile],
		/** @see \OCA\Talk\Controller\FilesIntegrationController::getRoomByShareToken() */
		['name' => 'FilesIntegration#getRoomByShareToken', 'url' => '/api/{apiVersion}/publicshare/{shareToken}', 'verb' => 'GET', 'requirements' => $requirementsShare],
	],
];
