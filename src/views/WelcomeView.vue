<template>
	<div id="emptycontent">
		<div id="emptycontent-icon" class="icon-talk" />
		<h2>{{ t('spreed', 'Join a conversation or start a new one') }}</h2>
		<p class="emptycontent-additional">
			{{ t('spreed', 'Say hi to your friends and colleagues!') }}
		</p>
	</div>
</template>

<script>
export default {
	name: 'WelcomeView',
}
</script>
