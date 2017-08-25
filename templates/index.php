<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */

vendor_script('select2/select2');
vendor_style('select2/select2');

style('spreed', 'style');
script(
	'spreed',
	[
		'vendor/backbone/backbone-min',
		'vendor/backbone.radio/build/backbone.radio.min',
		'vendor/backbone.marionette/lib/backbone.marionette.min',
		'vendor/pdfjs-dist/build/pdf.combined',
		'models/room',
		'models/roomcollection',
		'views/roomlistview',
		'simplewebrtc',
		'webrtc',
		'signaling',
		'calls',
		'presentations',
		'app',
		'init',
	]
);
?>

<div id="app" class="nc-enable-screensharing-extension" data-token="<?php p($_['token']) ?>">
	<div id="app-navigation" class="icon-loading">
		<form id="oca-spreedme-add-room">
			<input id="select-participants" class="select2-offscreen" type="text" placeholder="<?php p($l->t('Choose person …')) ?>"/>
		</form>
		<ul id="spreedme-room-list" class="with-icon">
		</ul>
	</div>

	<div id="app-content" class="participants-1">

		<button id="video-fullscreen" class="icon-fullscreen-white" data-placement="bottom" data-toggle="tooltip" data-original-title="<?php p($l->t('Fullscreen')) ?>"></button>

		<div id="video-speaking">

		</div>
		<div id="videos">
			<div class="videoView videoContainer hidden" id="localVideoContainer">
				<video id="localVideo"></video>
				<div class="avatar-container hidden">
					<div class="avatar"></div>
				</div>
				<div class="nameIndicator">
					<button id="mute" class="icon-audio-white" data-placement="top" data-toggle="tooltip" data-original-title="<?php p($l->t('Mute audio')) ?>"></button>
					<button id="hideVideo" class="icon-video-white" data-placement="top" data-toggle="tooltip" data-original-title="<?php p($l->t('Disable video')) ?>"></button>
					<button id="presentation-button" class="app-navigation-entry-utils-menu-button icon-upload-white screensharing-disabled" data-placement="top" data-toggle="tooltip" data-original-title="<?php p($l->t('Share presentation')) ?>"></button>
					<div id="presentation-menu" class="app-navigation-entry-menu">
						<ul>
							<li>
								<button id="presentation-next-page-button">
									<span class="icon-screen"></span>
									<span><?php p($l->t('Show next page')); ?></span>
								</button>
							</li>
							<li>
								<button id="presentation-previous-page-button">
									<span class="icon-screen"></span>
									<span><?php p($l->t('Show previous page')); ?></span>
								</button>
							</li>
							<li>
								<button id="stop-presentation-button">
									<span class="icon-screen-off"></span>
									<span><?php p($l->t('Close presentation')); ?></span>
								</button>
							</li>
						</ul>
					</div>
					<button id="screensharing-button" class="app-navigation-entry-utils-menu-button icon-screen-off-white screensharing-disabled" data-placement="top" data-toggle="tooltip" data-original-title="<?php p($l->t('Share screen')) ?>"></button>
					<div id="screensharing-menu" class="app-navigation-entry-menu">
						<ul>
							<li>
								<button id="show-screen-button">
									<span class="icon-screen"></span>
									<span><?php p($l->t('Show your screen'));?></span>
								</button>
							</li>
							<li>
								<button id="stop-screen-button">
									<span class="icon-screen-off"></span>
									<span><?php p($l->t('Stop screensharing'));?></span>
								</button>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<div id="screens"></div>

		<!-- TODO(leon): Load presentations inside sandboxed Iframe -->
		<div id="presentations"></div>

		<div id="emptycontent">
			<div id="emptycontent-icon" class="icon-video"></div>
			<h2><?php p($l->t('Looking great today! :)')) ?></h2>
			<p class="uploadmessage"><?php p($l->t('Time to call your friends')) ?></p>
			<div id="shareRoomContainer" class="" style="display: inline-flex">
				<input id="shareRoomInput" class="share-room-input hidden" readonly="readonly" type="text"/>
				<div id="shareRoomClipboardButton" class="shareRoomClipboard icon-clippy hidden" data-clipboard-target="#shareRoomInput"></div>
			</div>
		</div>
	</div>
</div>
