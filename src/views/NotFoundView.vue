<template>
	<div id="emptycontent">
		<div id="emptycontent-icon" class="icon-search" />
		<h2>{{ t('spreed', 'The conversation does not exist') }}</h2>
		<p class="emptycontent-additional">
			{{ t('spreed','Join a conversation or start a new one!') }}
		</p>
	</div>
</template>

<script>
export default {
	name: 'NotFoundView',
}
</script>
