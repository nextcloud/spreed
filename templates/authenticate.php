<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var array $_ */
/** @var \OCP\IL10N $l */
\OCP\Util::addStyle('core', 'publicshareauth');
\OCP\Util::addScript('core', 'publicshareauth');
?>
<div class="guest-box">
	<form method="post">
		<fieldset class="warning">
			<?php if ($_['showBruteForceWarning']) { ?>
				<div class="warning-info"><?php p($l->t('We have detected multiple invalid password attempts from your IP. Therefore your next attempt is throttled up to 30 seconds.')); ?></div>
			<?php } ?>
			<?php if (!$_['wrongpw']) { ?>
				<div class="warning-info"><?php p($l->t('This conversation is password-protected.')); ?></div>
			<?php } else { ?>
				<div class="warning"><?php p($l->t('The password is wrong. Try again.')); ?></div>
			<?php } ?>
			<p>
				<label for="password" class="infield"><?php p($l->t('Password')); ?></label>
				<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>" />
				<input type="password" name="password" id="password"
					placeholder="<?php p($l->t('Password')); ?>" value=""
					autocomplete="new-password" autocapitalize="off" autocorrect="off"
					autofocus />
				<input type="submit" id="password-submit"
					class="svg icon-confirm input-button-inline" value="" disabled="disabled" />
			</p>
		</fieldset>
	</form>
</div>
