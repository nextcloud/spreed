<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$receivedRequestsFile = sys_get_temp_dir() . '/fake-nextcloud-talk-external-call-requests';

if (preg_match('/\/nextcloud\/meeting\/([^\/]+)/', $_SERVER['REQUEST_URI'], $matches)) {
	echo json_encode([
		'targetUrl' => 'https://example.tld/webapp3/m/' . strrev($matches[1]),
		'hostPin' => '482916',
	]);
} else {
	header('HTTP/1.0 404 Not Found');
}
