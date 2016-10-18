<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */

style('spreed', 'style');
script(
	'spreed',
	[
		'vendor/backbone.radio/build/backbone.radio.min',
		'vendor/backbone.marionette/lib/backbone.marionette.min',
		'models/room',
		'models/roomcollection',
		'views/roomlistview',
		'simplewebrtc',
		'xhrconnection',
		'rooms',
		'webrtc',
		'app',
		'init',
	]
);
?>

<div id="app" data-sessionId="<?php p($_['sessionId']) ?>">
	<div id="app-navigation" class="icon-loading">
		<form id="oca-spreedme-add-room">
			<input id="edit-roomname" type="text" placeholder="<?php p($l->t('Choose room name …')) ?>"/>
			<button class="icon-confirm" title="<?php p($l->t('Create new room')) ?>"></button>
		</form>
		<ul>
		</ul>
		<!--<div id="app-settings">
			<div id="app-settings-header">
				<button class="settings-button"
						data-apps-slide-toggle="#app-settings-content"
				></button>
			</div>
			<div id="app-settings-content">
			</div>
		</div>-->
	</div>

	<div id="app-content">
		<div class="videoView hidden">
			<video id="localVideo"></video>
			<div class="nameIndicator">
				<button id="mute" class="icon-audio-white" data-title="<?php p($l->t('Mute audio')) ?>"></button>
				<button id="hideVideo" class="icon-video-white" data-title="<?php p($l->t('Pause video')) ?>"></button>
				<button id="video-more" class="icon-more-white" data-title="<?php p($l->t('More options')) ?>"></button>
			</div>
		</div>
		<div id="remotes" style="display: inline"></div>


		<div id="emptycontent">
			<div class="icon-video"></div>
			<h2><?php p($l->t('Time to do your hair! :)')) ?></h2>
			<p class="uploadmessage"><?php p($l->t('Then join a room or create a new one')) ?></p>
		</div>
	</div>
</div>
