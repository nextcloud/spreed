<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
script('spreed', ['admin/init', 'admin/templates', 'admin/signaling-server']);
style('spreed', ['settings-admin']);
?>

<div class="videocalls section signaling-server">
	<h2><?php p($l->t('Signaling server')) ?></h2>
	<p class="settings-hint"><?php p($l->t('An external signaling server can optionally be used for larger installations. Leave empty to use the internal signaling server.')) ?></p>

	<div class="signaling-servers">
	</div>

	<div class="signaling-secret">
		<h4><?php p($l->t('Shared secret')) ?></h4>
		<input type="text" id="signaling_secret"
			   name="signaling_secret" placeholder="<?php p($l->t('Shared secret')) ?>" aria-label="<?php p($l->t('Shared secret')) ?>"/>
	</div>
</div>
