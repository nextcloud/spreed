<template>
	<div id="emptycontent">
		<div id="emptycontent-icon" class="icon-info" />
		<h2>{{ t('spreed', 'Duplicate session') }}</h2>
		<p class="emptycontent-additional">
			{{ t('spreed','You joined the conversation in another window or device. This is currently not supported by Nextcloud Talk so this session was closed.') }}
		</p>
	</div>
</template>

<script>
export default {
	name: 'SessionConflictView',
}
</script>
