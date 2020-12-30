<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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


return array_merge_recursive(
	include(__DIR__ . '/routes/routesCallController.php'),
	include(__DIR__ . '/routes/routesChatController.php'),
	include(__DIR__ . '/routes/routesCommandController.php'),
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
	include(__DIR__ . '/routes/routesRoomController.php'),
	include(__DIR__ . '/routes/routesRoomAvatarController.php'),
	include(__DIR__ . '/routes/routesSettingsController.php'),
	include(__DIR__ . '/routes/routesSignalingController.php'),
	include(__DIR__ . '/routes/routesTempAvatarController.php'),
);
