<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
script('spreed', ['admin/init', 'admin/templates', 'admin/turn-server']);
script('spreed', ['admin/sha1']);
style('spreed', ['settings-admin']);
?>

<div class="videocalls section">
	<h2><?php p($l->t('TURN server')) ?></h2>
	<p class="settings-hint"><?php p($l->t('The TURN server is used to proxy the traffic from participants behind a firewall.')); ?></p>

	<div class="turn-servers">
	</div>
</div>
