<?php
/** @var \OCP\IL10N $l */
?>

<div id="admin_settings">
	<div class="videocalls section" id="general_settings">
		<h2><?php p($l->t('General settings')) ?></h2>
	</div>

	<div class="videocalls section" id="allowed_groups">
		<h2><?php p($l->t('Limit to groups')) ?></h2>
		<p class="settings-hint"><?php p($l->t('When at least one group is selected, only people of the listed groups can be part of conversations.')); ?></p>
		<p class="settings-hint"><?php p($l->t('Guests can still join public conversations.')); ?></p>
		<p class="settings-hint"><?php p($l->t('Users that cannot use Talk anymore will still be listed as participants in their previous conversations and also their chat messages will be kept.')); ?></p>
	</div>

	<div id="stun_server" class="videocalls section">
		<h2><?php p($l->t('STUN servers')) ?></h2>
		<p class="settings-hint"><?php p($l->t('A STUN server is used to determine the public IP address of participants behind a router.')); ?></p>

		<div class="stun-servers">
		</div>
	</div>

	<div id="turn_server" class="videocalls section">
		<h2><?php p($l->t('TURN server')) ?></h2>
		<p class="settings-hint"><?php p($l->t('The TURN server is used to proxy the traffic from participants behind a firewall.')); ?></p>

		<div class="turn-servers">
		</div>
	</div>

	<div id="signaling_server" class="videocalls section">
		<h2><?php p($l->t('Signaling servers')) ?></h2>
		<p class="settings-hint"><?php p($l->t('An external signaling server can optionally be used for larger installations. Leave empty to use the internal signaling server.')) ?></p>

		<div class="signaling-servers">
		</div>

		<div class="signaling-secret">
			<h4><?php p($l->t('Shared secret')) ?></h4>
			<input type="text" id="signaling_secret"
				   name="signaling_secret" placeholder="<?php p($l->t('Shared secret')) ?>" aria-label="<?php p($l->t('Shared secret')) ?>"/>
		</div>
	</div>

</div>
