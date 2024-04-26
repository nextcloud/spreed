<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\CertificateController::getCertificateExpiration() */
		['name' => 'Certificate#getCertificateExpiration', 'url' => '/api/{apiVersion}/certificate/expiration', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
