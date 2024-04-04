<!--
  - @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<TransitionGroup v-if="group"
		class="transition-group"
		:name="name"
		v-bind="$attrs"
		v-on="$listeners">
		<slot />
	</TransitionGroup>
	<Transition v-else
		:name="name"
		v-bind="$attrs"
		v-on="$listeners">
		<slot />
	</Transition>
</template>

<script>
export default {
	name: 'TransitionWrapper',

	inheritAttrs: false,

	props: {
		name: {
			type: String,
			default: undefined,
			validator(value) {
				return [
					'fade',
					'radial-reveal',
					'slide-up',
					'slide-right',
					'slide-down',
					'toast',
					'zoom',
				].includes(value)
			},
		},

		group: {
			type: Boolean,
			default: false,
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

// Transition rules (inside mixins to be applied in two places)
@mixin group-rules {
	&-move {
		transition: $transition;
	}
	&-leave-active {
		position: absolute;
	}
}

@mixin fade-rules {
	&-enter,
	&-leave-to {
		opacity: 0;
	}
	&-enter-to,
	&-leave {
		opacity: 1;
	}
	&-enter-active,
	&-leave-active {
		transition: $transition;
		transition-property: opacity;
	}
}

@mixin radial-reveal-rules {
	&-enter,
	&-leave-to {
		transform: scale(0);
		opacity: 0;
	}
	&-enter-to,
	&-leave {
		transform: scale(1);
		opacity: 1;
	}
	&-enter-active,
	&-leave-active {
		transition: $transition;
		transition-property: transform, opacity;
		transition-duration: 150ms;
	}
}

@mixin slide-up-rules {
	&-enter,
	&-leave-to {
		transform: translateY(-50%);
		opacity: 0;
	}
	&-enter-to,
	&-leave {
		transform: translateY(0);
		opacity: 1;
	}
	&-enter-active,
	&-leave-active {
		pointer-events: none;
		transition: $transition-slow;
		transition-property: transform, opacity;
	}
}

// TODO manipulate with transform: scaleX() instead of width
@mixin slide-right-rules {
	&-enter,
	&-leave-to {
		min-width: 0 !important;
		max-width: 0 !important;
	}
	&-enter-to,
	&-leave {
		min-width: 300px;
		max-width: 500px;
	}
	&-enter-active,
	&-leave-active {
		transition: $transition;
		transition-property: min-width, max-width;
	}
}

@mixin slide-down-rules {
	&-enter,
	&-leave-to {
		transform: translateY(50%);
		opacity: 0;
	}
	&-enter-to,
	&-leave {
		transform: translateY(0);
		opacity: 1;
	}
	&-enter-active,
	&-leave-active {
		transition: $transition;
		transition-property: transform, opacity;
		/* force top container to resize during animation */
		position: absolute !important;
	}
}

@mixin toast-rules {
	&-enter,
	&-leave-to {
		opacity: 0;
	}
	&-enter-to,
	&-leave {
		opacity: 1;
	}
	&-enter-active,
	&-leave-active {
		transition: $transition-slow;
		transition-property: opacity;
		transition-timing-function: linear;
	}
}

@mixin zoom-rules {
	&-enter,
	&-leave-to {
		transform: scale(0);
	}
	&-enter-to,
	&-leave {
		transform: scale(1);
	}
	&-enter-active,
	&-leave-active {
		transition: $transition;
		transition-property: transform;
	}
}

// Styles block for <transition> component
.fade {
	@include fade-rules;
}

.radial-reveal {
	@include radial-reveal-rules;
}

.slide-up {
	@include slide-up-rules;
}

.slide-right {
	@include slide-right-rules;
}

.slide-down {
	@include slide-down-rules;
}

.toast {
	@include toast-rules;
}

.zoom {
	@include zoom-rules;
}

// Styles block for for <transition-group> component
.transition-group {
	position: relative;

	& > :deep(*) {
		&.fade {
			@include group-rules;
			@include fade-rules;
		}
		&.radial-reveal {
			@include group-rules;
			@include radial-reveal-rules;
		}
		&.slide-up {
			@include group-rules;
			@include slide-up-rules;
		}
		&.slide-right {
			@include group-rules;
			@include slide-right-rules;
		}
		&.slide-down {
			@include group-rules;
			@include slide-down-rules;
		}
		&.toast {
			@include group-rules;
			@include toast-rules;
		}
		&.zoom {
			@include group-rules;
			@include zoom-rules;
		}
	}
}

</style>
