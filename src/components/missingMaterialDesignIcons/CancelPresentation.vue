<template functional>
	<span
		:aria-hidden="props.decorative"
		:aria-label="props.title"
		:class="[data.class, data.staticClass]"
		class="material-design-icon cancel-presentation-icon"
		role="img"
		v-bind="data.attrs"
		v-on="listeners">
		<svg
			:fill="props.fillColor"
			class="material-design-icon__svg"
			:width="props.size"
			:height="props.size"
			viewBox="0 0 24 24">
			<path d="M0 0h24v24H0z" fill="none" />
			<path d="M21 19.1H3V5h18v14.1zM21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" />
			<path d="M21 19.1H3V5h18v14.1zM21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" fill="none" />
			<path d="M14.59 8L12 10.59 9.41 8 8 9.41 10.59 12 8 14.59 9.41 16 12 13.41 14.59 16 16 14.59 13.41 12 16 9.41z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'CancelPresentation',
	props: {
		title: {
			type: String,
			default: 'Cancel Presentation icon',
		},
		decorative: {
			type: Boolean,
			default: false,
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>
