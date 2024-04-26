<?php
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var \OCP\Defaults $theme */
/** @var array $_ */
?>

<div id="clients-talk" class="section clientsbox">
	<h2><?php p($l->t('%s Talk on your mobile devices', [$theme->getName()]));?></h2>

	<p class="settings-hint"><?php p($l->t('Join conversations at any time, anywhere, on any device.')); ?></p>

	<div class="clientslinks">
		<a href="<?php p($_['clients']['android']); ?>" rel="noreferrer" target="_blank">
			<img src="<?php print_unescaped(image_path('core', 'googleplay.png')); ?>"
				 alt="<?php p($l->t('Android app'));?>" />
		</a>
		<a href="<?php p($_['clients']['ios']); ?>" rel="noreferrer" target="_blank">
			<img src="<?php print_unescaped(image_path('core', 'appstore.svg')); ?>"
				 alt="<?php p($l->t('iOS app'));?>" />
		</a>
	</div>
</div>
