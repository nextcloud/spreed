<template>
	<div class="message-main">
		<div v-if="isFirstMessage || isQuote" class="message-main-header">
			<h6>{{ userName }}</h6>
		</div>
		<div v-if="isQuote" class="quote">
			<slot />
		</div>
		<div class="message-main-text">
			<p>{{ messageText }}</p>
		</div>
	</div>
</template>

<script>
export default {
	props: {
		userName: {
			type: String,
			required: true
		},
		messageText: {
			type: String,
			required: true
		},
		isFirstMessage: {
			type: Boolean,
			default: false
		}
	},
	computed: {
		isQuote() {
			return this.$parent.messageText
		}
	}
}
</script>

<style lang="scss" scoped>
.wrapper {
	width: 100%;
	padding: 4px 0 4px 0;
	&:hover {
		background-color: rgba(47, 47, 47, 0.068);
	}
}

.message {
    &-main {
        display: flex;
		flex-grow: 1;
        flex-direction: column;
		font-size: 20;
		&-header {
			color: #989898;
		}
		&-text {
			color: #000000;
		}
	}
}
.quote {
	border-left: 4px solid #0083C9;
	padding: 4px 0 0 8px;
}
</style>
