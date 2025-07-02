<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


return array_merge_recursive(
	include(__DIR__ . '/routes/routesAvatarController.php'),
	include(__DIR__ . '/routes/routesBanController.php'),
	include(__DIR__ . '/routes/routesBotController.php'),
	include(__DIR__ . '/routes/routesBreakoutRoomController.php'),
	include(__DIR__ . '/routes/routesCalendarIntegrationController.php'),
	include(__DIR__ . '/routes/routesCallController.php'),
	include(__DIR__ . '/routes/routesCertificateController.php'),
	include(__DIR__ . '/routes/routesFederationController.php'),
	include(__DIR__ . '/routes/routesFilesIntegrationController.php'),
	include(__DIR__ . '/routes/routesGuestController.php'),
	include(__DIR__ . '/routes/routesHostedSignalingServerController.php'),
	include(__DIR__ . '/routes/routesMatterbridgeController.php'),
	include(__DIR__ . '/routes/routesMatterbridgeSettingsController.php'),
	include(__DIR__ . '/routes/routesPageController.php'),
	include(__DIR__ . '/routes/routesPollController.php'),
	include(__DIR__ . '/routes/routesPublicShareAuthController.php'),
	include(__DIR__ . '/routes/routesReactionController.php'),
	include(__DIR__ . '/routes/routesRecordingController.php'),
	include(__DIR__ . '/routes/routesRoomController.php'),
	include(__DIR__ . '/routes/routesSettingsController.php'),
	include(__DIR__ . '/routes/routesSignalingController.php'),
	include(__DIR__ . '/routes/routesTempAvatarController.php'),
);
