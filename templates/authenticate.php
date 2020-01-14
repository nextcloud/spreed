<?php
/** @var $_ array */
/** @var $l \OCP\IL10N */
style('core', 'publicshareauth');
script('core', 'publicshareauth');
?>
<form method="post">
	<fieldset class="warning">
		<?php if (!$_['wrongpw']) { ?>
			<div class="warning-info"><?php p($l->t('This conversation is password-protected')); ?></div>
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
