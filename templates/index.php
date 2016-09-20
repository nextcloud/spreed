<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */

style('spreed', 'style');
script(
	'spreed',
	[
		'simplewebrtc',
		'xhrconnection',
		'rooms',
		'webrtc',
		'index',
	]
);
?>

<div id="app" data-sessionId="<?php p($_['sessionId']) ?>">
	<div id="app-navigation" class="icon-loading">
		<ul>
		</ul>
		<div id="app-settings">
			<div id="app-settings-header">
				<button class="settings-button"
						data-apps-slide-toggle="#app-settings-content"
				></button>
			</div>
			<div id="app-settings-content">
				<form id="oca-spreedme-add-room">
					<input type="text" placeholder="<?php p($l->t('Room name…')) ?>"/>
					<input type="submit" value="<?php p($l->t('Create new room')) ?>"/>
				</form>
			</div>
		</div>
	</div>

	<div id="app-content">
		<div class="videoView hidden">
			<video id="localVideo"></video>
			<div class="nameIndicator">
				<button id="mute" class="icon-audio-white" data-title="<?php p($l->t('Mute audio')) ?>"></button>
				<button id="hideVideo" class="icon-video-white" data-title="<?php p($l->t('Pause video')) ?>"></button>
			</div>
		</div>
		<div id="remotes" style="display: inline"></div>


		<div id="emptycontent">
			<h2><?php p($l->t('Not in any room')) ?></h2>
			<p class="uploadmessage"><?php p($l->t('Choose a room to the left or create a new one.')) ?></p>
		</div>
	</div>
</div>
