<template functional>
	<span :aria-hidden="props.decorative"
		:aria-label="props.title"
		:class="[data.class, data.staticClass]"
		class="material-design-icon present-to-all-icon"
		role="img"
		v-bind="data.attrs"
		v-on="listeners">
		<svg :fill="props.fillColor"
			class="material-design-icon__svg"
			:width="props.size"
			:height="props.size"
			viewBox="0 0 16 16">
			<rect x="1"
				y="1"
				width="13"
				height="9" />
			<rect x="1"
				y="12"
				width="3"
				height="3" />
			<rect x="6"
				y="12"
				width="3"
				height="3" />
			<rect x="11"
				y="12"
				width="3"
				height="3" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'PromotedView',
	props: {
		title: {
			type: String,
			default: 'Promoted View icon',
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
