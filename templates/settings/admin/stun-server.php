<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
script('spreed', ['admin/stun-server', 'admin/templates']);
style('spreed', ['settings-admin']);
?>

<div class="videocalls section">
	<h2><?php p($l->t('STUN servers')) ?></h2>
	<p class="settings-hint"><?php p($l->t('A STUN server is used to determine the public IP address of participants behind a router.')); ?></p>

	<div class="stun-servers" data-servers="<?php p($_['stunServer']) ?>">
	</div>
</div>
