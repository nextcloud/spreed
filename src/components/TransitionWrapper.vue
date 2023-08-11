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
	<Transition v-bind="$attrs" :name="name">
		<slot />
	</Transition>
</template>

<script>
export default {
	name: 'TransitionWrapper',
	props: {
		name: {
			type: String,
			default: 'default', // could be undefined?
			validator(value) {
				return [
					'default',
					'fade',
					'slide-up',
					'slide-right',
					'slide-down',
				].includes(value)
			},
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/variables';

.fade {
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
		transition: $fade-transition;
	}
}

.slide-up {
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
		transition: $fade-transition-slow;
	}
}

.slide-right {
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
		transition-duration: var(--animation-quick);
		transition-property: min-width, max-width;
	}
}

.slide-down {
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
		transition: $fade-transition;
		/* force top container to resize during animation */
		position: absolute !important;
	}
}
</style>
