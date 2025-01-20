<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'ocs' => [
		/** @see \OCA\TalkWebhookDemo\Controller\BotController::receiveWebhook() */
		['name' => 'Bot#receiveWebhook', 'url' => '/api/v1/bot/{lang}', 'verb' => 'POST'],
	],
];
