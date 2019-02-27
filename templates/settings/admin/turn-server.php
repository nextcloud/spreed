<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
script('spreed', ['admin/turn-server', 'admin/templates']);
script('spreed', ['admin/sha1']);
style('spreed', ['settings-admin']);
?>

<div class="videocalls section">
	<h2><?php p($l->t('TURN server')) ?></h2>
	<p class="settings-hint"><?php p($l->t('The TURN server is used to proxy the traffic from participants behind a firewall.')); ?></p>

	<div class="turn-servers" data-servers="<?php p($_['turnServer']) ?>">
	</div>
</div>
