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
		<input type="checkbox" id="signaling_skip_verify_cert" name="signaling_skip_verify_cert" class="checkbox" value="1" <?php p($_['signalingSkipVerifyCert'] ? 'checked="checked"' : '') ?>>
		<label for="signaling_skip_verify_cert"><?php p($l->t('Allow invalid certificates when connecting to the external signaling server? Only enable this if required for development!')) ?>
		</label>
	</p>
	<p>
		<label for="signaling_secret"><?php p($l->t('Shared secret')) ?></label>
		<input type="text" id="signaling_secret"
			   name="signaling_secret" placeholder="shared secret"
			   value="<?php p($_['signalingSecret']) ?>" />
	</p>
</div>
