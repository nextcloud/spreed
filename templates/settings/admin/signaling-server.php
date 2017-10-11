<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
script('spreed', ['admin/signaling-server']);
style('spreed', ['settings-admin']);
?>

<div class="videocalls section signaling-server">
	<h3><?php p($l->t('Signaling server')) ?></h3>
	<p class="settings-hint"><?php p($l->t('An external signaling server can optionally be used for larger installations. Leave empty to use the internal signaling server.')) ?></p>

	<div class="signaling-servers" data-servers="<?php p($_['signalingServers']) ?>">
	</div>

	<p class="hidden">
		<label for="signaling_secret"><?php p($l->t('Shared secret')) ?></label>
		<input type="text" id="signaling_secret"
			   name="signaling_secret" placeholder="<?php p($l->t('Shared secret')) ?>"
			   value="<?php p($_['signalingSecret']) ?>" />
	</p>
</div>
