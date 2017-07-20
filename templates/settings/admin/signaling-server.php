<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
script('spreed', ['admin/signaling-server']);
style('spreed', ['settings-admin']);
?>

<div class="videocalls section signaling-server">
	<h3><?php p($l->t('Signaling server')) ?></h3>
	<p class="settings-hint"><?php p($l->t('An external signaling server can optionally be used for larger installations. Leave empty to use the internal signaling server.')) ?></p>

	<p>
		<label for="signaling_server"><?php p($l->t('External signaling server')) ?></label>
		<input type="text" id="signaling_server"
			   name="signaling_server" placeholder="wss://signaling.example.org"
			   value="<?php p($_['signalingServer']) ?>" />
	</p>
	<p>
		<label for="signaling_secret"><?php p($l->t('Shared secret')) ?></label>
		<input type="text" id="signaling_secret"
			   name="signaling_secret" placeholder="shared secret"
			   value="<?php p($_['signalingSecret']) ?>" />
	</p>
</div>
