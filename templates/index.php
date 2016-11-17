<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */

vendor_script('select2/select2');
vendor_style('select2/select2');

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
		'webrtc',
		'xhrconnection',
		'rooms',
		'app',
		'init',
	]
);
?>

<div id="app" data-roomId="<?php p($_['roomId']) ?>">
	<div id="app-navigation" class="icon-loading">
		<form id="oca-spreedme-add-room">
			<input id="edit-roomname" type="text" placeholder="<?php p($l->t('Choose person …')) ?>"/>
			<!-- <button class="icon-confirm" title="<?php p($l->t('Create new room')) ?>"></button> -->
		</form>
		<ul id="spreedme-room-list" class="with-icon">
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

	<div id="app-content" class="participants-1">

		<div id="video-speaking">

		</div>
		<div id="videos">
			<div class="videoView videoContainer hidden" id="localVideoContainer">
				<video id="localVideo"></video>
				<div class="avatar-container hidden">
					<div class="avatar"></div>
				</div>
				<div class="nameIndicator">
					<button id="mute" class="icon-audio-white" data-title="<?php p($l->t('Mute audio')) ?>"></button>
					<button id="hideVideo" class="icon-video-white" data-title="<?php p($l->t('Pause video')) ?>"></button>
					<button id="video-fullscreen" class="icon-fullscreen-white" data-title="<?php p($l->t('Fullscreen')) ?>"></button>
				</div>
			</div>
		</div>


		<div id="emptycontent">
			<div class="icon-video"></div>
			<h2><?php p($l->t('Looking great today! :)')) ?></h2>
			<p class="uploadmessage"><?php p($l->t('Time to call your friends')) ?></p>
		</div>
	</div>
</div>
