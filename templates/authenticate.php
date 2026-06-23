<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var \OCP\IL10N $l */
/** @var array{share: \OCP\Share\IShare, identityOk?: bool, wrongpw?: bool} $_ */
\OCP\Util::addStyle('spreed', 'talk-public-share-auth-form');
\OCP\Util::addScript('spreed', 'talk-public-share-auth-form');

$initialState = \OCP\Server::get(\OCP\IInitialStateService::class);
$initialState->provideInitialState('spreed', 'talk-public-share-auth', [
	'showBruteForceWarning' => $_['showBruteForceWarning'] ?? null,
	'wrongpw' => $_['wrongpw'] ?? null,
]);
?>
<div class="hidden-visually"><?php p($l->t('This conversation is password-protected.')); ?></div>
<div id="talk-guest-auth"></div>
